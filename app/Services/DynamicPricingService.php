<?php

namespace App\Services;

use App\Models\Product;

class DynamicPricingService
{
    /**
     * Get the dynamic price for a specific product and company.
     *
     * @param Product $product
     * @param int|null $companyId
     * @return float
     */
    public function getPrice(Product $product, ?int $companyId): float
    {
        if (!$companyId) {
            return (float) $product->base_price;
        }

        // We can optimize this by loading relation conditionally or using a cached lookup if needed.
        $customPriceList = $product->priceLists()->where('company_id', $companyId)->first();

        if ($customPriceList) {
            return (float) $customPriceList->custom_price;
        }

        return (float) $product->base_price;
    }

    /**
     * Get dynamic prices for a collection of products.
     *
     * @param \Illuminate\Database\Eloquent\Collection $products
     * @param int|null $companyId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function applyPricesToCollection($products, ?int $companyId)
    {
        if (!$companyId) {
            return $products;
        }

        // Eager load the price list for this specific company to avoid N+1 query problem
        $products->load(['priceLists' => function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        }]);

        foreach ($products as $product) {
            $customPriceList = $product->priceLists->first();
            $price = $customPriceList ? (float) $customPriceList->custom_price : (float) $product->base_price;
            $product->dynamic_price = $price;
            $product->base_price = $price; // Overwrite base_price dynamically for 100% frontend compatibility
        }

        return $products;
    }

    /**
     * Resolve dynamic price by productId and companyId (helper for CartController).
     *
     * @param int $productId
     * @param int $companyId
     * @return float|null
     */
    public function resolvePrice(int $productId, int $companyId): ?float
    {
        $product = Product::find($productId);
        if (!$product) {
            return null;
        }
        return $this->getPrice($product, $companyId);
    }
}
