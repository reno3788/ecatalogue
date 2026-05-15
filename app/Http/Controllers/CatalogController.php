<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\DynamicPricingService;
use Inertia\Inertia;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    protected DynamicPricingService $pricingService;

    public function __construct(DynamicPricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        
        $query = Product::where('is_active', true)->with(['images', 'categories']);

        // Overall max/min price for the slider
        $priceRange = [
            'min' => Product::where('is_active', true)->min('base_price') ?? 0,
            'max' => Product::where('is_active', true)->max('base_price') ?? 5000,
        ];

        // Brands and counts
        $brands = Product::where('is_active', true)
                    ->whereNotNull('brand')
                    ->where('brand', '!=', '')
                    ->select('brand')
                    ->selectRaw('count(*) as count')
                    ->groupBy('brand')
                    ->orderBy('brand')
                    ->get();

        // Categories with product count
        $categories = \App\Models\Category::getWithRecursiveProductCounts(
            \App\Models\Category::orderBy('name')
        );

        // Filters
        if ($request->has('category') && $request->category) {
            $category = \App\Models\Category::where('slug', $request->category)->first();
            if ($category) {
                $categoryIds = $category->getAllDescendantIds();
                $query->whereHas('categories', function ($q) use ($categoryIds) {
                    $q->whereIn('categories.id', $categoryIds);
                });
            }
        }

        if ($request->has('q') && $request->q) {
            $query->where('name', 'like', '%' . $request->q . '%')
                  ->orWhere('sku', 'like', '%' . $request->q . '%')
                  ->orWhere('brand', 'like', '%' . $request->q . '%');
        }

        if ($request->has('brands') && is_array($request->brands) && count($request->brands) > 0) {
            $query->whereIn('brand', $request->brands);
        }

        if ($request->has('min_price') && is_numeric($request->min_price)) {
            $query->where('base_price', '>=', $request->min_price);
        }

        if ($request->has('max_price') && is_numeric($request->max_price)) {
            $query->where('base_price', '<=', $request->max_price);
        }

        if ($request->has('sort')) {
            switch ($request->sort) {
                case 'price_asc':
                    $query->orderBy('base_price', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('base_price', 'desc');
                    break;
                case 'relevance':
                default:
                    $query->orderBy('id', 'desc');
                    break;
            }
        } else {
            $query->orderBy('id', 'desc'); // default
        }

        $products = $query->get();

        // Apply B2B Pricing if user is assigned to a company
        if ($user && $user->company_id) {
            $products = $this->pricingService->applyPricesToCollection($products, $user->company_id);
        }

        return Inertia::render('Catalog/Index', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'priceRange' => $priceRange,
            'filters' => [
                'category' => $request->category,
                'q' => $request->q ?? '',
                'brands' => $request->brands ?? [],
                'min_price' => $request->min_price ?? $priceRange['min'],
                'max_price' => $request->max_price ?? $priceRange['max'],
                'sort' => $request->sort ?? 'relevance',
            ]
        ]);
    }

    public function show(\App\Models\Product $product)
    {
        $product->load(['images', 'categories']);
        
        $user = auth()->user();
        if ($user && $user->company_id) {
            $product->base_price = app(\App\Services\DynamicPricingService::class)->getPrice($product, $user->company_id);
        }

        return Inertia::render('Catalog/Show', [
            'product' => $product
        ]);
    }
}
