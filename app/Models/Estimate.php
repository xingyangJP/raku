<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estimate extends Model
{
    protected $fillable = [
        'customer_name',
        'client_id',
        'title',
        'issue_date',
        'due_date',
        'status',
        'total_amount',
        'tax_amount',
        'notes',
        'items',
        'estimate_number',
        'staff_id',
        'staff_name',
        'approval_flow',
    ];

    protected $casts = [
        'items' => 'array',
        'issue_date' => 'date',
        'due_date' => 'date',
        'approval_flow' => 'array',
    ];

    // No local FK relation for staff; staff_id refers to external directory
}
