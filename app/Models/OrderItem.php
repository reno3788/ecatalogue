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
        'original_price',
        'supplier_offered_price',
        'buyer_requested_price',
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
