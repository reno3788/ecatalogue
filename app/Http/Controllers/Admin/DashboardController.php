<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // 1. Parse universal filters immediately
        $selectedPeriod = $request->input('period'); 
        $groupBy = $request->input('group_by', 'company'); 

        // 2. Construct Base Date-Scoped Order Query
        // EXCLUDE rejected orders from analytical performance calculations
        $baseQuery = Order::query()->where('status', '!=', 'Rejected');
        if ($selectedPeriod && strpos($selectedPeriod, '-') !== false) {
            [$yr, $mo] = explode('-', $selectedPeriod);
            $baseQuery->whereYear('created_at', $yr)->whereMonth('created_at', $mo);
        }

        // 3. Fetch Date-Scoped Datasets for the Dashboard
        // This powers both the Orders Table and the Core Value Stats
        $orders = $baseQuery->clone()->with(['user', 'company'])->latest()->get();
        
        $customers = User::with('company')
            ->whereHas('roles', function ($q) {
                $q->where('name', 'like', '%buyer%');
            })
            ->latest()
            ->get();

        // 4. Compute Performance Analytics (Reuses exactly the same base time filters)
        $chartData = [];

        // 4. Compute Performance Analytics (Dynamic Multivariate Pivot Matrix)
        // Cache hierarchy map for ALL calculations to determine root categories
        $allCats = Category::all();
        $rootMap = [];
        $catNames = $allCats->pluck('name', 'id')->toArray();
        foreach ($allCats as $cat) {
            $curr = $cat;
            while ($curr->parent_id) {
                $p = $allCats->firstWhere('id', $curr->parent_id);
                if (!$p || $p->id === $curr->id) break; 
                $curr = $p;
            }
            $rootMap[$cat->id] = $curr->id;
        }

        // Load data with deep nesting to fetch ALL dimension intersect points
        $ordersWithItems = $baseQuery->clone()->with(['items.product.categories', 'company'])->get();
        $pivotRaw = [];

        foreach ($ordersWithItems as $order) {
            $compName = $order->company->name ?? 'Unregistered Company';

            foreach ($order->items as $item) {
                $price = (float) ($item->price ?? 0);
                $qty = (int) ($item->quantity ?? 1);
                $itemTotal = round($price * $qty, 2);

                $prodCats = $item->product->categories ?? collect();
                $primaryCat = $prodCats->first();
                $rootId = $primaryCat ? ($rootMap[$primaryCat->id] ?? $primaryCat->id) : 'Uncategorized';
                $rootName = ($rootId === 'Uncategorized') ? 'Uncategorized' : ($catNames[$rootId] ?? 'Unknown');

                // Determine logic axis: Primary=X-Axis label, Secondary=Internal Stacked Segments
                $primaryKey = ($groupBy === 'category') ? $rootName : $compName;
                $secondaryKey = ($groupBy === 'category') ? $compName : $rootName;

                if (!isset($pivotRaw[$primaryKey])) {
                    $pivotRaw[$primaryKey] = [];
                }
                if (!isset($pivotRaw[$primaryKey][$secondaryKey])) {
                    $pivotRaw[$primaryKey][$secondaryKey] = ['total' => 0, 'count' => 0];
                }

                $pivotRaw[$primaryKey][$secondaryKey]['total'] += $itemTotal;
                $pivotRaw[$primaryKey][$secondaryKey]['count'] += $qty; // Using aggregated item unit quantities as Volume metric
            }
        }

        // Convert RAW nested matrix into UI-friendly flat structured list
        $chartData = [];
        foreach ($pivotRaw as $primaryName => $secondaryGroup) {
            $segments = [];
            $sumTotal = 0;
            $sumCount = 0;

            foreach ($secondaryGroup as $secName => $vals) {
                $sumTotal += $vals['total'];
                $sumCount += $vals['count'];
                $segments[] = [
                    'label' => $secName,
                    'total' => round($vals['total'], 2),
                    'count' => $vals['count']
                ];
            }

            // Sort internal segments biggest-first
            usort($segments, fn($a, $b) => $b['total'] <=> $a['total']);

            $chartData[] = [
                'label' => $primaryName,
                'overallTotal' => round($sumTotal, 2),
                'overallCount' => $sumCount,
                'segments' => $segments
            ];
        }

        // Finally, sort columns by total overall volume to present impactful first
        $chartData = collect($chartData)->sortByDesc('overallTotal')->values()->toArray();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_products' => Product::count(),
                'total_categories' => Category::count(),
                'total_orders' => $orders->count(), // Date filtered!
                'total_revenue' => round($orders->sum('total'), 2), // Date filtered!
            ],
            'orders' => $orders,
            'customers' => $customers,
            'chartData' => $chartData,
            'filters' => [
                'period' => $selectedPeriod,
                'group_by' => $groupBy,
            ]
        ]);
    }
}
