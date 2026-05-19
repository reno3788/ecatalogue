<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\QuotationMail;

class OrderNegotiationController extends Controller
{
    /**
     * Supplier/Admin submits an offer (finalized pricing) to the Buyer.
     */
    public function submitOffer(Request $request, Order $order)
    {
        // ... authorize ...
        
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:order_items,id',
            'items.*.supplier_offered_price' => 'required|numeric|min:0',
            'comment' => 'nullable|string',
        ]);

        $targetStatus = 'RFQ';

        DB::transaction(function () use ($request, $order, &$targetStatus) {
            $newTotal = 0;
            foreach ($request->items as $itemData) {
                $orderItem = $order->items()->find($itemData['id']);
                if ($orderItem) {
                    // Initialize original price if it's the first offer
                    $originalPrice = $orderItem->original_price ?? $orderItem->price;
                    
                    $orderItem->update([
                        'original_price' => $originalPrice,
                        'supplier_offered_price' => $itemData['supplier_offered_price'],
                    ]);
                    $newTotal += ($itemData['supplier_offered_price'] * $orderItem->quantity);
                }
            }

            // Update order total first to ensure accurate workflow evaluation
            $order->update(['total' => $newTotal]);

            // Resolve whether an active workflow gates the transition to Quotation
            $workflowService = app(\App\Services\WorkflowService::class);
            $workflow = $workflowService->getApplicableWorkflow($order);
            $targetStatus = $workflow ? 'Submitted' : 'Quotation';

            OrderHistory::create([
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'action' => 'Supplier Offer',
                'comment' => $request->comment,
                'previous_status' => $order->status,
                'new_status' => $targetStatus,
            ]);

            $order->update(['status' => $targetStatus]);
        });

        if ($targetStatus === 'Quotation') {
            // Send email to buyer
            try {
                $recipient = $order->user;
                if ($recipient && $recipient->email) {
                    Mail::to($recipient->email)->send(new QuotationMail($order));
                }
            } catch (\Exception $e) {
                Log::error("Quotation email failure for Order {$order->id}: " . $e->getMessage());
            }

            // Notify buyer users
            $buyers = \App\Models\User::where('company_id', $order->company_id)
                ->whereHas('roles', function($q) {
                    $q->whereIn('name', ['buyer', 'buyer_requester']);
                })
                ->get();
            if ($buyers->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send($buyers, new \App\Notifications\OrderStatusNotification($order, "A quotation for order #{$order->id} is ready for your review."));
            }
        }

        return back()->with([
            'flash_type' => 'success',
            'flash_message' => 'Offer submitted successfully.'
        ]);
    }

    /**
     * Buyer requests a better price (counter-offer).
     */
    public function requestBetterPrice(Request $request, Order $order)
    {
        $company = $order->company;
        if ($company) {
            if ($company->punchout_enabled || !empty($order->punchout_po_reference)) {
                return back()->withErrors([
                    'items' => 'Bargaining is not allowed for PunchOut orders.'
                ]);
            }
            if (!$company->bargaining_enabled) {
                return back()->withErrors([
                    'items' => 'Bargaining is disabled for your company.'
                ]);
            }
        }

        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:order_items,id',
            'items.*.buyer_requested_price' => 'required|numeric|gt:0',
            'comment' => 'required|string', // Mandatory comment for bargaining
        ]);

        // Custom validation for price and tolerance
        foreach ($request->items as $itemData) {
            $orderItem = $order->items()->with('product')->find($itemData['id']);
            if ($orderItem) {
                $offeredPrice = $orderItem->supplier_offered_price !== null 
                    ? floatval($orderItem->supplier_offered_price) 
                    : floatval($orderItem->price);
                
                $requestedPrice = floatval($itemData['buyer_requested_price']);
                
                if ($requestedPrice <= 0) {
                    return back()->withErrors([
                        'items' => "Bargain price for '{$orderItem->product->name}' cannot be empty or 0."
                    ]);
                }

                if ($requestedPrice > $offeredPrice) {
                    return back()->withErrors([
                        'items' => "Requested price for '{$orderItem->product->name}' cannot exceed the offered price of " . number_format($offeredPrice, 2) . "."
                    ]);
                }

                $tolerance = floatval($orderItem->product->tolerance_percentage ?? 0);
                if ($tolerance > 0) {
                    $minPrice = $offeredPrice * (1 - $tolerance / 100);
                    if ($requestedPrice < $minPrice) {
                        return back()->withErrors([
                            'items' => "We are sorry we cannot accept the price for '{$orderItem->product->name}'."
                        ]);
                    }
                }
            }
        }

        DB::transaction(function () use ($request, $order) {
            $bargainedItems = [];
            foreach ($request->items as $itemData) {
                $orderItem = $order->items()->with('product')->find($itemData['id']);
                if ($orderItem) {
                    $offeredPrice = $orderItem->supplier_offered_price !== null 
                        ? floatval($orderItem->supplier_offered_price) 
                        : floatval($orderItem->price);

                    $bargainedItems[] = [
                        'name' => $orderItem->product->name ?? $orderItem->name ?? 'Unknown Item',
                        'offered_price' => $offeredPrice,
                        'bargain_price' => floatval($itemData['buyer_requested_price']),
                    ];

                    $orderItem->update([
                        'buyer_requested_price' => $itemData['buyer_requested_price'],
                    ]);
                }
            }

            OrderHistory::create([
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'action' => 'Buyer Bargain',
                'comment' => json_encode([
                    'type' => 'bargain',
                    'text' => $request->comment,
                    'items' => $bargainedItems,
                ]),
                'previous_status' => $order->status,
                'new_status' => Order::STATUS_RFQ, // Stays in RFQ but needs attention
            ]);

            $order->update(['status' => Order::STATUS_RFQ]); // Revert to RFQ so supplier can review again
        });

        return back()->with([
            'flash_type' => 'success',
            'flash_message' => 'Bargain request sent to supplier.'
        ]);
    }

    /**
     * Buyer accepts the supplier's offer and converts to PO.
     */
    public function acceptOffer(Request $request, Order $order)
    {
        $request->validate([
            'po_attachment' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        DB::transaction(function () use ($request, $order) {
            // Finalize pricing
            $newTotal = 0;
            foreach ($order->items as $item) {
                if ($item->supplier_offered_price !== null) {
                    $item->update(['price' => $item->supplier_offered_price]);
                }
                $newTotal += ($item->price * $item->quantity);
            }

            $path = $request->file('po_attachment')->store('po_attachments', 'public');

            OrderHistory::create([
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'action' => 'Accept Offer (PO Created)',
                'previous_status' => $order->status,
                'new_status' => Order::STATUS_PO,
            ]);

            $order->update([
                'status' => Order::STATUS_PO,
                'total' => $newTotal,
                'po_attachment' => '/storage/' . $path,
            ]);
        });

        // Notify Admin and Supplier roles
        $recipients = \App\Models\User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['admin', 'supplier_processor', 'supplier_approver', 'supplier_admin']);
        })->get();

        if ($recipients->isNotEmpty()) {
            \Illuminate\Support\Facades\Notification::send($recipients, new \App\Notifications\OrderStatusNotification($order, "Buyer has uploaded a PO for order #{$order->id} and submitted it."));
            
            try {
                Mail::to($recipients)->send(new \App\Mail\PoSubmittedMail($order));
            } catch (\Exception $e) {
                Log::error('Failed to send PO submission email: ' . $e->getMessage());
            }
        }

        return back()->with([
            'flash_type' => 'success',
            'flash_message' => 'Offer accepted. PO generated successfully.'
        ]);
    }
}
