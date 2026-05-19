<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderHistory extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'action',
        'comment',
        'previous_status',
        'new_status',
    ];

    protected $appends = [
        'status_before',
        'status_after',
    ];

    public function getStatusBeforeAttribute()
    {
        return $this->previous_status;
    }

    public function getStatusAfterAttribute()
    {
        return $this->new_status;
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
