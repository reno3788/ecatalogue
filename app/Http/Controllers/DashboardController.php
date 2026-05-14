<?php
 
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $isSupplierApprover = $user->hasRole('supplier_approver');

        // Non-admin base dashboard guard
        if (!$user->company_id && !$isSupplierApprover) {
            return Inertia::render('Dashboard', [
                'orders' => ['data' => [], 'links' => []],
                'filters' => $request->only(['month', 'status']),
                'summary' => [
                    'RFQ' => 0,
                    'Quotation' => 0,
                    'PO' => 0,
                    'Completed' => 0,
                ],
            ])->with('error', 'You are not assigned to a company.');
        }

        // Base query: Supplier Approvers see ALL orders, others only see company orders
        if ($isSupplierApprover) {
            $baseQuery = Order::query();
        } else {
            $baseQuery = Order::where('company_id', $user->company_id);
        }

        // Apply period (month) filter BEFORE summary to ensure cards react to selected period
        $monthFilter = $request->input('month');
        if ($monthFilter) {
            $date = Carbon::parse($monthFilter);
            $baseQuery->whereYear('created_at', $date->year)
                      ->whereMonth('created_at', $date->month);
        }

        // Build summary for current period filter
        // Map logic: Get status counts efficiently
        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $summary = [
            // Aggregate internally hidden seller steps into initial RFQ bucket for buyer visibility
            'RFQ' => ($statusCounts['RFQ'] ?? 0) + ($statusCounts['Submitted'] ?? 0) + ($statusCounts['Approved'] ?? 0),
            'Quotation' => $statusCounts['Quotation'] ?? 0,
            // Aggregate order confirmation and shipping progress under official PO bucket
            'PO' => ($statusCounts['PO'] ?? 0) + ($statusCounts['Invoiced'] ?? 0) + ($statusCounts['Shipped'] ?? 0),
            'Completed' => $statusCounts['Completed'] ?? 0,
        ];

        // Second layer filter: Status selection for the TABLE data grid
        $gridQuery = (clone $baseQuery)
            ->with(['user'])
            ->withCount('items')
            ->latest();

        if ($request->filled('status')) {
            if ($request->status === 'RFQ') {
                $gridQuery->whereIn('status', ['RFQ', 'Submitted', 'Approved']);
            } else {
                $gridQuery->where('status', $request->status);
            }
        }

        $orders = $gridQuery->paginate(10)->withQueryString();

        return Inertia::render('Dashboard', [
            'orders' => $orders,
            'summary' => $summary,
            'filters' => $request->only(['month', 'status']),
            'statuses' => ['RFQ', 'Quotation', 'PO', 'Invoiced', 'Shipped', 'Completed', 'Rejected'],
        ]);
    }
}
