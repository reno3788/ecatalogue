<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class OrderController extends Controller
{
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
        ]);

        $data = ['status' => $request->status];
        if ($request->status === 'Rejected') {
            $data['rejection_reason'] = $request->rejection_reason;
        } else {
            // Clear rejection reason if changed to something else
            $data['rejection_reason'] = null;
        }

        $order->update($data);

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

        $updateData = ['status' => $request->status];
        if ($request->status === 'Rejected') {
            $updateData['rejection_reason'] = $request->rejection_reason;
        } else {
            $updateData['rejection_reason'] = null;
        }

        Order::whereIn('id', $request->order_ids)->update($updateData);

        return back()->with([
            'flash_type' => 'success',
            'flash_message' => "Successfully updated " . count($request->order_ids) . " orders to {$request->status}.",
        ]);
    }
    
    public function show(Order $order)
    {
        $order->load(['company', 'user', 'items.product']);
        return response()->json($order);
    }
}
