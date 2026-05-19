<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return redirect()->route('dashboard', $request->query());
    }
    
    public function show(Order $order)
    {
        // Ensure authorization: either own company or supplier approver role
        $isSupplierApprover = auth()->user()->hasRole('supplier_approver');
        if (!$isSupplierApprover && $order->company_id !== auth()->user()->company_id) {
            abort(403);
        }
        
        $order->load([
            'company', 
            'items.product', 
            'user', 
            'approvalLogs.user', 
            'histories.user', 
            'carrier',
            'shipments.carrier',
            'shipments.items.orderItem.product'
        ]);
        return response()->json($order);
    }

    public function updateStatus(Request $request, Order $order)
    {
        // Ensure authorization
        $isSupplierApprover = auth()->user()->hasRole('supplier_approver');
        if (!$isSupplierApprover && $order->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $rules = [
            'status' => 'required|in:PO',
        ];

        if ($request->input('status') === 'PO') {
            $rules['po_attachment'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:5120';
        }

        $request->validate($rules);

        // Double safeguard: Users can only advance their permitted states
        if ($request->status === 'PO' && !in_array($order->status, ['Approved', 'Quotation'])) {
            return back()->withErrors(['status' => 'You can only generate a PO once a valid Quotation is received.']);
        }

        $updateData = ['status' => $request->status];

        if ($request->hasFile('po_attachment')) {
            $path = $request->file('po_attachment')->store('po_attachments', 'public');
            $updateData['po_attachment'] = '/storage/' . $path;
        }

        $order->update($updateData);

        if ($request->status === 'PO') {
            $suppliers = \App\Models\User::whereHas('roles', function($q) {
                $q->whereIn('name', ['admin', 'supplier_processor', 'supplier_approver']);
            })->get();
            \Illuminate\Support\Facades\Notification::send($suppliers, new \App\Notifications\OrderStatusNotification($order, 'Buyer has generated a PO for order #' . $order->id));
        }

        return back()->with([
            'flash_type' => 'success',
            'flash_message' => "Order status updated to {$request->status}.",
        ]);
    }
}
