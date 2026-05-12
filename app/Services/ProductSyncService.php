<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductSyncService
{
    /**
     * Syncs a single product based on an array of data.
     * Expected data: sku, name, description, base_price, is_active, image, weight, color, size, brand, additional_images
     *
     * @param array $data
     * @return Product
     */
    public function syncProduct(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::updateOrCreate(
                ['sku' => $data['sku']],
                [
                    'name' => $data['name'] ?? null,
                    'description' => $data['description'] ?? null,
                    'base_price' => $data['base_price'] ?? 0,
                    'is_active' => isset($data['is_active']) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : true,
                    'image' => $data['image'] ?? null,
                    'weight' => isset($data['weight']) && $data['weight'] !== '' ? (float) $data['weight'] : null,
                    'color' => $data['color'] ?? null,
                    'size' => $data['size'] ?? null,
                    'brand' => $data['brand'] ?? null,
                ]
            );

            // Handle additional images if provided
            if (isset($data['additional_images']) && is_array($data['additional_images'])) {
                $this->syncProductImages($product, $data['additional_images']);
            } elseif (isset($data['additional_images']) && is_string($data['additional_images'])) {
                $images = array_filter(array_map('trim', explode(',', $data['additional_images'])));
                $this->syncProductImages($product, $images);
            }

            // Handle categories
            if (isset($data['categories'])) {
                $categoryNames = array_filter(array_map('trim', explode(',', $data['categories'])));
                $categoryIds = [];
                foreach ($categoryNames as $catName) {
                    $slug = \Illuminate\Support\Str::slug($catName);
                    $category = \App\Models\Category::firstOrCreate(
                        ['slug' => $slug],
                        ['name' => $catName]
                    );
                    $categoryIds[] = $category->id;
                }
                $product->categories()->sync($categoryIds);
            }

            return $product;
        });
    }

    /**
     * Sync multiple products.
     *
     * @param array $products
     * @return array
     */
    public function syncBatch(array $products): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($products as $index => $productData) {
            try {
                if (empty($productData['sku'])) {
                    throw new \Exception("SKU is required.");
                }
                $this->syncProduct($productData);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Row {$index}: " . $e->getMessage();
                Log::error("Product Sync Error (SKU: " . ($productData['sku'] ?? 'N/A') . "): " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Syncs multiple images for a product.
     * This replaces the current additional images with the new list.
     *
     * @param Product $product
     * @param array $imageUrls
     * @return void
     */
    protected function syncProductImages(Product $product, array $imageUrls): void
    {
        // For simplicity, we drop existing and recreate. 
        // In a complex app, you might want to only add/remove differences to preserve IDs.
        $product->images()->delete();

        $imagesToInsert = [];
        foreach ($imageUrls as $url) {
            if (!empty($url)) {
                $imagesToInsert[] = [
                    'product_id' => $product->id,
                    'image_path' => $url,
                    'is_primary' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($imagesToInsert)) {
            $product->images()->insert($imagesToInsert);
        }
    }
}
