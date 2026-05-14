<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Order extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'company_id',
        'user_id',
        'status',
        'punchout_po_reference',
        'total',
        'rejection_reason',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function approvalLogs()
    {
        return $this->hasMany(OrderApprovalLog::class)->latest();
    }
}
