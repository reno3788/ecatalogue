<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Workflow extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
        'company_id',
        'min_amount',
        'require_sequential',
        'is_active',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'require_sequential' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function steps()
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('sort_order', 'asc');
    }
}
