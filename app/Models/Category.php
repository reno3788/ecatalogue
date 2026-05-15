<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Category extends Model
{
    use Auditable;
    protected $fillable = ['name', 'slug', 'parent_id'];

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * Get ID of this category and all its recursive descendant categories.
     * 
     * @return array
     */
    public function getAllDescendantIds()
    {
        $ids = [$this->id];
        // Ensure children are loaded to avoid multiple queries if already eager loaded
        foreach ($this->children()->get() as $child) {
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }
        return $ids;
    }

    public static function getWithRecursiveProductCounts($query = null)
    {
        $query = $query ?: static::query();
        $categories = $query->get();
        
        // Load all product-category mappings once to compute counts efficiently in memory
        $mappings = \DB::table('category_product')->get();
        
        // Group product IDs by category ID
        $directProducts = [];
        foreach ($mappings as $map) {
            $directProducts[$map->category_id][] = $map->product_id;
        }
        
        // Build category map for traversal
        $allCats = $categories->keyBy('id');
        
        // Cache for computed unique product IDs for each category
        $uniqueProductsCache = [];
        
        $getUniqueProducts = function($id) use (&$allCats, &$directProducts, &$uniqueProductsCache, &$getUniqueProducts) {
            if (isset($uniqueProductsCache[$id])) {
                return $uniqueProductsCache[$id];
            }
            
            $productIds = $directProducts[$id] ?? [];
            
            // Find direct children
            foreach ($allCats as $cat) {
                if ($cat->parent_id !== null && (int)$cat->parent_id === (int)$id) {
                    $productIds = array_merge($productIds, $getUniqueProducts($cat->id));
                }
            }
            
            $uniqueIds = array_values(array_unique($productIds));
            $uniqueProductsCache[$id] = $uniqueIds;
            
            return $uniqueIds;
        };
        
        foreach ($categories as $cat) {
            $count = count($getUniqueProducts($cat->id));
            $cat->setAttribute('products_count', $count);
        }
        
        return $categories;
    }
}
