<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Product extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'base_price',
        'minimum_order',
        'is_active',
        'image',
        'weight',
        'color',
        'size',
        'brand',
        'uom',
        'classification',
        'manufacturer_part_id',
        'manufacturer_name',
        'tolerance_percentage',
    ];

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function priceLists()
    {
        return $this->hasMany(ClientPriceList::class);
    }

    public function catalogVisibility()
    {
        return $this->hasMany(CatalogVisibility::class);
    }

    // Dynamic pricing helper
    public function getPriceForCompany($companyId)
    {
        $customPrice = $this->priceLists()->where('company_id', $companyId)->first();
        return $customPrice ? $customPrice->custom_price : $this->base_price;
    }
}
