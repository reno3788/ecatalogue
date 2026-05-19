<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\QuotationMail;

class OrderController extends Controller
{
    protected $workflowService;

    public function __construct(WorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }
    public function index(Request $request)
    {
        $query = Order::with(['company', 'user', 'carrier'])
            ->withCount('items')
            ->latest();

        // Month Filter (expects format YYYY-MM)
        if ($request->filled('month')) {
            $date = Carbon::parse($request->month);
            $query->whereYear('created_at', $date->year)
                  ->whereMonth('created_at', $date->month);
        }

        // Status Filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Company Filter
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // We can fetch unique months that have orders for the drop down options
        // Or just build it in Frontend dynamically. Let's build in Frontend.
        
        $orders = $query->paginate(10)->withQueryString();
        $companies = Company::select('id', 'name')->get();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'companies' => $companies,
            'filters' => $request->only(['month', 'status', 'company_id']),
            'statuses' => ['RFQ', 'Submitted', 'Approved', 'Quotation', 'PO', 'Partially Shipped', 'Shipped', 'Invoiced', 'Completed', 'Rejected'],
            'carriers' => \App\Models\Carrier::all(),
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:RFQ,Submitted,Approved,Quotation,PO,Partially Shipped,Shipped,Invoiced,Completed,Rejected',
            'rejection_reason' => 'required_if:status,Rejected|nullable|string|max:500',
            'note' => 'nullable|string|max:500',
            'carrier_id' => 'required_if:status,Shipped|nullable|exists:carriers,id',
            'tracking_number' => 'required_if:status,Shipped|nullable|string|max:255',
        ]);

        // 1. Intercept specific Workflow Triggers: Moving FROM Submitted TO Approved
        if ($this->workflowService->isWorkflowTransition($order->status, $request->status)) {
            // Perform hard restriction check
            if (!$this->workflowService->canUserApprove($order, $request->user())) {
                return back()->withErrors(['status' => 'You are not authorized to approve this order at the current step.']);
            }
            
            try {
                // Advance the workflow logic!
                $nextStatus = $this->workflowService->executeWorkflowAction($order, $request->user(), $request->note);
                
                // Final promotion if complete, otherwise remains in Submitted for next step!
                $order->update(['status' => $nextStatus]);

                if ($nextStatus === 'Quotation') {
                    $suppliers = \App\Models\User::whereHas('roles', function($q) {
                        $q->whereIn('name', ['admin', 'supplier_processor', 'supplier_approver']);
                    })->get();
                    \Illuminate\Support\Facades\Notification::send($suppliers, new \App\Notifications\OrderStatusNotification($order, 'Order #' . $order->id . ' has been Approved and moved to Quotation.'));
                    
                    // Notify buyers
                    $this->notifyCompanyBuyers($order, 'Quotation');

                    try {
                        $recipient = $order->user;
                        if ($recipient && $recipient->email) {
                            Mail::to($recipient->email)->send(new QuotationMail($order));
                        }
                    } catch (\Exception $mailEx) {
                        Log::error("Workflow completion quotation email failure for Order {$order->id}: " . $mailEx->getMessage());
                    }
                }
                
                $msg = ($nextStatus === 'Quotation') 
                    ? "All approval steps complete. Order successfully updated to Quotation!"
                    : "Workflow step approved and logged successfully. Order remains pending next step.";
                    
                return back()->with([
                    'flash_type' => 'success',
                    'flash_message' => $msg,
                ]);
                
            } catch (\Exception $e) {
                return back()->withErrors(['status' => $e->getMessage()]);
            }
        }

        // 2. Standard Manual Bypass logic for all other generic states
        $data = ['status' => $request->status];
        if ($request->status === 'Rejected') {
            $data['rejection_reason'] = $request->rejection_reason;
            
            // Optional: Record failure trail
            \App\Models\OrderApprovalLog::create([
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'action' => 'Rejected',
                'note' => $request->rejection_reason
            ]);

            // Notify buyers
            $this->notifyCompanyBuyers($order, 'Rejected', $request->rejection_reason);
        } else {
            $data['rejection_reason'] = null;
            
            // Log standard promotion trails if transitioning beyond workflow
            if ($order->status !== $request->status) {
                \App\Models\OrderApprovalLog::create([
                    'order_id' => $order->id,
                    'user_id' => $request->user()->id,
                    'action' => 'Status Update',
                    'note' => 'Manually promoted state to ' . $request->status
                ]);
            }

            // Save dynamic shipping information if status is Shipped
            if ($request->status === 'Shipped') {
                $data['carrier_id'] = $request->carrier_id;
                $data['tracking_number'] = $request->tracking_number;
            }
        }

        $order->update($data);

        // Notify if it's manually approved or updated to Quotation
        if (in_array($request->status, ['Approved', 'Quotation'])) {
            $suppliers = \App\Models\User::whereHas('roles', function($q) {
                $q->whereIn('name', ['admin', 'supplier_processor', 'supplier_approver']);
            })->get();
            \Illuminate\Support\Facades\Notification::send($suppliers, new \App\Notifications\OrderStatusNotification($order, 'Order #' . $order->id . ' has been updated to ' . $request->status . '.'));
        }

        // Notify buyers for Invoiced, Shipped, or other statuses (manual update fallback)
        if (in_array($request->status, ['Invoiced', 'Shipped'])) {
            $this->notifyCompanyBuyers($order, $request->status);
        }

        // 3. Event-Driven Action: Auto-send premium notification if promoting to Approved or Quotation phase.
        if (in_array($request->status, ['Approved', 'Quotation'])) {
            try {
                // Notify buyers
                $this->notifyCompanyBuyers($order, 'Quotation');

                $recipient = $order->user;
                if ($recipient && $recipient->email) {
                    Mail::to($recipient->email)->send(new QuotationMail($order));
                    
                    return back()->with([
                        'flash_type' => 'success',
                        'flash_message' => 'Success! Order status updated to ' . $request->status . ' and Quotation PDF generated & emailed to client.',
                    ]);
                }
            } catch (\Exception $mailEx) {
                Log::error("Quotation email failure for Order {$order->id}: " . $mailEx->getMessage());
                
                return back()->with([
                    'flash_type' => 'warning',
                    'flash_message' => 'Status updated to ' . $request->status . ', but email dispatch failed. Please review SMTP configurations.',
                ]);
            }
        }

        return back()->with([
            'flash_type' => 'success',
            'flash_message' => "Order status updated to {$request->status}.",
        ]);
    }

    public function batchUpdateStatus(Request $request)
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:orders,id',
            'status' => 'required|in:RFQ,Submitted,Approved,Quotation,PO,Partially Shipped,Shipped,Invoiced,Completed,Rejected',
            'rejection_reason' => 'required_if:status,Rejected|nullable|string|max:500',
        ]);

        $orders = Order::whereIn('id', $request->order_ids)->get();
        $successCount = 0;
        $failedOrders = [];

        // Define chronological hierarchy: only allow forward transitions (lower index to higher index)
        $statusRank = [
            'RFQ'               => 1,
            'Submitted'         => 2,
            'Approved'          => 3,
            'Quotation'         => 4,
            'PO'                => 5,
            'Partially Shipped' => 6,
            'Shipped'           => 7,
            'Invoiced'          => 8,
            'Completed'         => 9,
            'Rejected'          => 10,
        ];

        $targetStatus = $request->status;
        $targetRank = $statusRank[$targetStatus] ?? 0;

        $updateData = ['status' => $targetStatus];
        if ($targetStatus === 'Rejected') {
            $updateData['rejection_reason'] = $request->rejection_reason;
        } else {
            $updateData['rejection_reason'] = null;
        }

        // Process each order individually to ensure workflow compliance
        foreach ($orders as $order) {
            $currentRank = $statusRank[$order->status] ?? 0;

            // ENFORCE FORWARD DIRECTION: Block if attempting to go backwards
            if ($targetRank < $currentRank) {
                $failedOrders[] = "ID#{$order->id} (Cannot revert back to an earlier phase)";
                continue;
            }

            // If already at target, skip silently without error
            if ($order->status === $targetStatus) {
                continue; 
            }

            // CASE: Attempting to move TO Approved/Quotation (Triggers potential Workflow gating)
            if (in_array($targetStatus, ['Approved', 'Quotation'])) {
                // Does this order transition trigger a workflow lockdown?
                if ($this->workflowService->isWorkflowTransition($order->status, $targetStatus)) {
                    // Check specific permission
                    if (!$this->workflowService->canUserApprove($order, $request->user())) {
                        $failedOrders[] = "ID#{$order->id} (Insufficent workflow privileges)";
                        continue;
                    }
                    
                    try {
                        // Advance step
                        $nextStatus = $this->workflowService->executeWorkflowAction($order, $request->user(), "Batch Approved");
                        $order->update(['status' => $nextStatus]);
                        $successCount++;
                    } catch (\Exception $e) {
                        $failedOrders[] = "ID#{$order->id} (" . $e->getMessage() . ")";
                    }
                    continue;
                } 
            }

            // Standard Manual / Generic Update
            // Log standard manual state leap (e.g., RFQ -> Submitted, or Approved -> PO)
            if ($order->status !== $targetStatus) {
                \App\Models\OrderApprovalLog::create([
                    'order_id' => $order->id,
                    'user_id' => $request->user()->id,
                    'action' => 'Status Update',
                    'note' => 'Batch status changed to ' . $targetStatus
                ]);
            }
            
            $order->update($updateData);
            
            // Notify buyers for relevant status changes in batch
            if (in_array($targetStatus, ['Approved', 'Quotation', 'Invoiced', 'Shipped', 'Rejected'])) {
                $this->notifyCompanyBuyers($order, $targetStatus, $request->rejection_reason);
            }

            $successCount++;
        }

        $msg = "Successfully processed {$successCount} order(s).";
        
        if (count($failedOrders) > 0) {
            $msg .= " WARNING: Failed to update " . count($failedOrders) . " order(s) due to workflow restrictions: " . implode(', ', array_slice($failedOrders, 0, 5));
            if (count($failedOrders) > 5) {
                $msg .= "... and " . (count($failedOrders) - 5) . " more.";
            }
            
            return back()->with([
                'flash_message' => $msg, // Will show as Amber Alert in frontend
            ]);
        }

        return back()->with([
            'success' => $msg, // Will show as Emerald Success in frontend
        ]);
    }
    
    public function show(Request $request, Order $order)
    {
        $order->load([
            'company', 
            'user', 
            'items.product', 
            'approvalLogs.user', 
            'histories.user', 
            'carrier',
            'shipments.carrier',
            'shipments.items.orderItem.product'
        ]);
        
        // Inject runtime accessibility calculated metadata directly into response payload
        $wf = $this->workflowService->getApplicableWorkflow($order);
        $isWfTrans = ($order->status === 'Submitted');
        
        $orderData = $order->toArray();
        $orderData['workflow_enabled'] = !!$wf;
        $orderData['can_approve'] = $isWfTrans ? $this->workflowService->canUserApprove($order, $request->user()) : false;
        
        // Append the latest company-specific price for syncing
        foreach ($orderData['items'] as &$item) {
            if (isset($item['product'])) {
                $productModel = $order->items->firstWhere('id', $item['id'])->product;
                if ($productModel) {
                    $item['latest_sync_price'] = $productModel->getPriceForCompany($order->company_id);
                }
            }
        }
        
        return response()->json($orderData);
    }

    public function createShipmentForm(Order $order)
    {
        $order->load([
            'company',
            'user',
            'items.product',
            'shipments.carrier',
            'shipments.items.orderItem.product'
        ]);

        $carriers = \App\Models\Carrier::all();

        return Inertia::render('Admin/Orders/CreateShipment', [
            'order' => $order,
            'carriers' => $carriers,
        ]);
    }

    public function createShipment(Request $request, Order $order)
    {
        $request->validate([
            'carrier_id' => 'required|exists:carriers,id',
            'tracking_number' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array',
            'items.*.order_item_id' => 'required|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:0',
        ]);

        $hasShippedQty = false;
        foreach ($request->items as $itemData) {
            $orderItem = \App\Models\OrderItem::findOrFail($itemData['order_item_id']);
            
            $previouslyShipped = \App\Models\ShipmentItem::where('order_item_id', $orderItem->id)->sum('quantity');
            $remaining = $orderItem->quantity - $previouslyShipped;

            if ($itemData['quantity'] > 0) {
                $hasShippedQty = true;
            }

            if ($itemData['quantity'] > $remaining) {
                return back()->withErrors([
                    'items' => "The quantity for item '{$orderItem->product->name}' cannot exceed the remaining unshipped quantity of {$remaining}."
                ]);
            }
        }

        if (!$hasShippedQty) {
            return back()->withErrors([
                'items' => "At least one item must have a shipping quantity greater than 0."
            ]);
        }

        $shipment = null;
        \Illuminate\Support\Facades\DB::transaction(function() use ($request, $order, &$shipment) {
            $shipment = \App\Models\Shipment::create([
                'order_id' => $order->id,
                'carrier_id' => $request->carrier_id,
                'tracking_number' => $request->tracking_number,
                'notes' => $request->notes,
            ]);

            foreach ($request->items as $itemData) {
                if ($itemData['quantity'] > 0) {
                    \App\Models\ShipmentItem::create([
                        'shipment_id' => $shipment->id,
                        'order_item_id' => $itemData['order_item_id'],
                        'quantity' => $itemData['quantity'],
                    ]);
                }
            }

            $settings = \App\Models\AppSetting::first();
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.delivery_note', [
                'shipment' => $shipment->load(['order.user', 'order.company', 'carrier', 'items.orderItem.product']),
                'order' => $order,
                'settings' => $settings
            ]);

            $pdfDir = storage_path('app/public/delivery_notes');
            if (!file_exists($pdfDir)) {
                mkdir($pdfDir, 0755, true);
            }
            $pdfPath = 'delivery_notes/DN_' . str_pad($shipment->id, 6, '0', STR_PAD_LEFT) . '_' . time() . '.pdf';
            $pdf->save(storage_path('app/public/' . $pdfPath));

            $shipment->update(['delivery_note_path' => $pdfPath]);

            $allFullyShipped = true;
            foreach ($order->items as $orderItem) {
                $totalShipped = \App\Models\ShipmentItem::where('order_item_id', $orderItem->id)->sum('quantity');
                if ($totalShipped < $orderItem->quantity) {
                    $allFullyShipped = false;
                    break;
                }
            }

            $nextStatus = $allFullyShipped ? 'Shipped' : 'Partially Shipped';
            $order->update(['status' => $nextStatus]);

            \App\Models\OrderApprovalLog::create([
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'action' => 'Shipment Created',
                'note' => "Created Shipment #DN-" . str_pad($shipment->id, 6, '0', STR_PAD_LEFT) . " via " . ($shipment->carrier->name ?? 'Manual Delivery') . ". Tracking Code: " . $shipment->tracking_number
            ]);

            try {
                $recipient = $order->user;
                if ($recipient && $recipient->email) {
                    Mail::to($recipient->email)->send(new \App\Mail\DeliveryNoteMail($shipment));
                }
            } catch (\Exception $mailEx) {
                Log::error("Delivery Note email failure for Shipment {$shipment->id}: " . $mailEx->getMessage());
            }

            $this->notifyCompanyBuyers($order, $nextStatus);
        });

        return redirect()->route('admin.orders.index', ['open_order' => $order->id, 'tab' => 'shipping'])->with([
            'flash_type' => 'success',
            'flash_message' => "Shipment #DN-" . str_pad($shipment->id, 6, '0', STR_PAD_LEFT) . " successfully generated! Delivery note has been emailed to the buyer."
        ]);
    }

    public function uploadInvoice(Request $request, Order $order)
    {
        $request->validate([
            'invoice_file' => 'required|file|mimes:pdf,jpg,jpeg,png,xlsx,docx|max:10240',
            'additional_documents' => 'nullable|array',
            'additional_documents.*' => 'file|mimes:pdf,jpg,jpeg,png,xlsx,docx|max:10240',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function() use ($request, $order) {
            if ($request->hasFile('invoice_file')) {
                $file = $request->file('invoice_file');
                $filename = 'Invoice_' . str_pad($order->id, 6, '0', STR_PAD_LEFT) . '_' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('invoices', $filename, 'public');
                $order->invoice_attachment = $path;
            }

            $docPaths = [];
            if ($request->hasFile('additional_documents')) {
                foreach ($request->file('additional_documents') as $doc) {
                    $docName = $doc->getClientOriginalName();
                    $cleanName = pathinfo($docName, PATHINFO_FILENAME) . '_' . time() . '.' . $doc->getClientOriginalExtension();
                    $docPath = $doc->storeAs('invoices/docs', $cleanName, 'public');
                    $docPaths[] = [
                        'name' => $docName,
                        'path' => $docPath
                    ];
                }
                $order->invoice_documents = $docPaths;
            }

            $order->status = 'Invoiced';
            $order->save();

            \App\Models\OrderApprovalLog::create([
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'action' => 'Invoiced',
                'note' => "Supplier uploaded official invoice and associated documents."
            ]);

            $this->notifyCompanyBuyers($order, 'Invoiced');
        });

        return back()->with([
            'flash_type' => 'success',
            'flash_message' => "Invoice and supporting documents successfully uploaded! Buyer has been notified."
        ]);
    }

    private function notifyCompanyBuyers(Order $order, string $status, ?string $reason = null)
    {
        $buyers = \App\Models\User::where('company_id', $order->company_id)
            ->whereHas('roles', function($q) {
                $q->whereIn('name', ['buyer', 'buyer_requester']);
            })
            ->get();

        if ($buyers->isEmpty()) {
            return;
        }

        $message = match ($status) {
            'Approved' => "A quotation for order #{$order->id} is ready for your review.",
            'Quotation' => "A quotation for order #{$order->id} is ready for your review.",
            'Partially Shipped' => "Order #{$order->id} has been partially shipped.",
            'Invoiced' => "An invoice has been generated for your order #{$order->id}.",
            'Shipped' => "Order #{$order->id} has been shipped and is on its way.",
            'Rejected' => "Order #{$order->id} has been rejected." . ($reason ? " Reason: {$reason}" : ""),
            default => "Order #{$order->id} status updated to {$status}.",
        };

        \Illuminate\Support\Facades\Notification::send($buyers, new \App\Notifications\OrderStatusNotification($order, $message));
    }
}
