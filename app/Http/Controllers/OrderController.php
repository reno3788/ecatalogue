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
        $user = $request->user();
        $isSupplierApprover = $user->hasRole('supplier_approver');

        // Ensure the user belongs to a company or is a supplier approver
        if (!$user->company_id && !$isSupplierApprover) {
            return Inertia::render('Orders/Index', [
                'orders' => ['data' => [], 'links' => []],
                'filters' => $request->only(['month', 'status']),
                'statuses' => ['RFQ', 'Submitted', 'Approved', 'Quotation', 'PO', 'Invoiced', 'Shipped', 'Completed', 'Rejected'],
            ])->with('error', 'You are not assigned to a company.');
        }

        if ($isSupplierApprover) {
            $query = Order::query();
        } else {
            $query = Order::where('company_id', $user->company_id);
        }

        $query->with(['user'])
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

        $orders = $query->paginate(10)->withQueryString();

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['month', 'status']),
            'statuses' => ['RFQ', 'Submitted', 'Approved', 'Quotation', 'PO', 'Invoiced', 'Shipped', 'Completed', 'Rejected'],
        ]);
    }
    
    public function show(Order $order)
    {
        // Ensure authorization: either own company or supplier approver role
        $isSupplierApprover = auth()->user()->hasRole('supplier_approver');
        if (!$isSupplierApprover && $order->company_id !== auth()->user()->company_id) {
            abort(403);
        }
        
        $order->load(['items.product', 'user', 'approvalLogs.user']);
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
            'status' => 'required|in:PO,Completed',
        ];

        if ($request->input('status') === 'PO') {
            $rules['po_attachment'] = 'required|file|mimes:pdf,jpg,jpeg,png|max:5120';
        }

        $request->validate($rules);

        // Double safeguard: Users can only advance their permitted states
        if ($request->status === 'PO' && $order->status !== 'Quotation') {
            return back()->withErrors(['status' => 'You can only generate a PO once a valid Quotation is received.']);
        }
        if ($request->status === 'Completed' && $order->status !== 'Shipped') {
            return back()->withErrors(['status' => 'Receipt confirmation is only permitted after the order is shipped.']);
        }

        $updateData = ['status' => $request->status];

        if ($request->hasFile('po_attachment')) {
            $path = $request->file('po_attachment')->store('po_attachments', 'public');
            $updateData['po_attachment'] = '/storage/' . $path;
        }

        $order->update($updateData);

        return back()->with([
            'flash_type' => 'success',
            'flash_message' => "Order status updated to {$request->status}.",
        ]);
    }
}
