<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class OrderItem extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'uom',
        'classification',
        'manufacturer_part_id',
        'manufacturer_name',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
