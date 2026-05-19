<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Order extends Model
{
    use HasFactory, Auditable;

    const STATUS_RFQ = 'RFQ';
    const STATUS_APPROVED = 'Approved';
    const STATUS_PO = 'PO';
    const STATUS_SHIPPED = 'Shipped';
    const STATUS_RECEIPT = 'Receipt';
    const STATUS_INVOICED = 'Invoiced';
    const STATUS_COMPLETE = 'Complete';

    protected $fillable = [
        'company_id',
        'user_id',
        'status',
        'carrier_id',
        'tracking_number',
        'po_attachment',
        'invoice_attachment',
        'invoice_documents',
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

    protected $casts = [
        'invoice_documents' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function approvalLogs()
    {
        return $this->hasMany(OrderApprovalLog::class)->latest();
    }

    public function histories()
    {
        return $this->hasMany(OrderHistory::class)->latest();
    }
}
