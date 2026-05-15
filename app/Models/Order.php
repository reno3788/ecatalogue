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
        'po_attachment',
        'punchout_po_reference',
        'total',
        'rejection_reason',
        'deployment_mode',
        'po_date',
        'currency',
        'shipping_name',
        'shipping_email',
        'shipping_address',
        'billing_name',
        'billing_email',
        'billing_address',
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
