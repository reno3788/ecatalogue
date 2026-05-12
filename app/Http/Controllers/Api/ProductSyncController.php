<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductSyncController extends Controller
{
    protected ProductSyncService $productSyncService;

    public function __construct(ProductSyncService $productSyncService)
    {
        $this->productSyncService = $productSyncService;
    }

    /**
     * Handle a batch sync of products.
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'products' => 'required|array',
            'products.*.sku' => 'required|string',
            'products.*.name' => 'required|string',
            'products.*.base_price' => 'required|numeric|min:0',
            // other fields are optional
        ]);

        $results = $this->productSyncService->syncBatch($request->input('products'));

        return response()->json([
            'message' => 'Batch sync completed',
            'results' => $results
        ], 200);
    }
}
