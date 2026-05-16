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
        $query = Order::with(['company', 'user'])
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
            'statuses' => ['RFQ', 'Submitted', 'Approved', 'Quotation', 'PO', 'Invoiced', 'Shipped', 'Completed', 'Rejected'],
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:RFQ,Submitted,Approved,Quotation,PO,Invoiced,Shipped,Completed,Rejected',
            'rejection_reason' => 'required_if:status,Rejected|nullable|string|max:500',
            'note' => 'nullable|string|max:500',
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

                if ($nextStatus === 'Approved') {
                    $suppliers = \App\Models\User::whereHas('roles', function($q) {
                        $q->whereIn('name', ['admin', 'supplier_processor', 'supplier_approver']);
                    })->get();
                    \Illuminate\Support\Facades\Notification::send($suppliers, new \App\Notifications\OrderStatusNotification($order, 'Order #' . $order->id . ' has been Approved. Please send a quotation.'));
                    
                    // Notify buyers
                    $this->notifyCompanyBuyers($order, 'Approved');
                }
                
                $msg = ($nextStatus === 'Approved') 
                    ? "All approval steps complete. Order fully Approved!"
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
            
            // Optional: Log standard promotion trails if transitioning beyond workflow
            if ($order->status !== $request->status) {
                \App\Models\OrderApprovalLog::create([
                    'order_id' => $order->id,
                    'user_id' => $request->user()->id,
                    'action' => 'Status Update',
                    'note' => 'Manually promoted state to ' . $request->status
                ]);
            }
        }

        $order->update($data);

        // Notify if it's manually approved
        if ($request->status === 'Approved') {
            $suppliers = \App\Models\User::whereHas('roles', function($q) {
                $q->whereIn('name', ['admin', 'supplier_processor', 'supplier_approver']);
            })->get();
            \Illuminate\Support\Facades\Notification::send($suppliers, new \App\Notifications\OrderStatusNotification($order, 'Order #' . $order->id . ' has been manually Approved. Please send a quotation.'));
            
            // Notify buyers
            $this->notifyCompanyBuyers($order, 'Approved');
        }

        // Notify buyers for Invoiced, Shipped, or other statuses (manual update fallback)
        if (in_array($request->status, ['Invoiced', 'Shipped'])) {
            $this->notifyCompanyBuyers($order, $request->status);
        }

        // 3. Event-Driven Action: Auto-send premium notification if promoting to Quotation phase.
        if ($request->status === 'Quotation') {
            try {
                // Notify buyers
                $this->notifyCompanyBuyers($order, 'Quotation');

                $recipient = $order->user;
                if ($recipient && $recipient->email) {
                    Mail::to($recipient->email)->send(new QuotationMail($order));
                    
                    return back()->with([
                        'flash_type' => 'success',
                        'flash_message' => 'Success! Quotation PDF generated and emailed to client.',
                    ]);
                }
            } catch (\Exception $mailEx) {
                Log::error("Quotation email failure for Order {$order->id}: " . $mailEx->getMessage());
                
                return back()->with([
                    'flash_type' => 'warning',
                    'flash_message' => 'Status updated, but email dispatch failed. Please review SMTP configurations.',
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
            'status' => 'required|in:RFQ,Submitted,Approved,Quotation,PO,Invoiced,Shipped,Completed,Rejected',
            'rejection_reason' => 'required_if:status,Rejected|nullable|string|max:500',
        ]);

        $orders = Order::whereIn('id', $request->order_ids)->get();
        $successCount = 0;
        $failedOrders = [];

        // Define chronological hierarchy: only allow forward transitions (lower index to higher index)
        $statusRank = [
            'RFQ'       => 1,
            'Submitted' => 2,
            'Approved'  => 3,
            'Quotation' => 4,
            'PO'        => 5,
            'Invoiced'  => 6,
            'Shipped'   => 7,
            'Completed' => 8,
            'Rejected'  => 9,
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

            // CASE: Attempting to move TO Approved (Triggers potential Workflow gating)
            if ($targetStatus === 'Approved') {
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
                
                // If it's already Approved or in non-workflow status, does user still allow manual jump?
                // In general updateStatus allows it, so loop through. 
                // But strictly, only admins should jump beyond it. Let's assume standard manual jump fallback.
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
        $order->load(['company', 'user', 'items.product', 'approvalLogs.user']);
        
        // Inject runtime accessibility calculated metadata directly into response payload
        $wf = $this->workflowService->getApplicableWorkflow($order);
        $isWfTrans = ($order->status === 'Submitted');
        
        $orderData = $order->toArray();
        $orderData['workflow_enabled'] = !!$wf;
        $orderData['can_approve'] = $isWfTrans ? $this->workflowService->canUserApprove($order, $request->user()) : false;
        
        return response()->json($orderData);
    }

    private function notifyCompanyBuyers(Order $order, string $status, ?string $reason = null)
    {
        // Use whereHas instead of role() to avoid "Role not found" exceptions
        $buyers = \App\Models\User::where('company_id', $order->company_id)
            ->whereHas('roles', function($q) {
                $q->whereIn('name', ['buyer', 'buyer_requester']);
            })
            ->get();

        if ($buyers->isEmpty()) {
            return;
        }

        $message = match ($status) {
            'Approved' => "Your order #{$order->id} has been approved and is awaiting quotation.",
            'Quotation' => "A quotation for order #{$order->id} is ready for your review.",
            'Invoiced' => "An invoice has been generated for your order #{$order->id}.",
            'Shipped' => "Order #{$order->id} has been shipped and is on its way.",
            'Rejected' => "Order #{$order->id} has been rejected." . ($reason ? " Reason: {$reason}" : ""),
            default => "Order #{$order->id} status updated to {$status}.",
        };

        \Illuminate\Support\Facades\Notification::send($buyers, new \App\Notifications\OrderStatusNotification($order, $message));
    }
}
