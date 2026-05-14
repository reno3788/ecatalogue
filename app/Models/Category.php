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
}
