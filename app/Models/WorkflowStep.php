<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'sort_order',
        'name',
        'description',
    ];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function approvers()
    {
        return $this->belongsToMany(User::class, 'workflow_step_users');
    }
}
