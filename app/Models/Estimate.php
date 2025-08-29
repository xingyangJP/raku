<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estimate extends Model
{
    protected $fillable = [
        'customer_name',
        'title',
        'issue_date',
        'due_date',
        'status',
        'total_amount',
        'tax_amount',
        'notes',
        'items',
        'estimate_number',
    ];

    protected $casts = [
        'items' => 'array',
        'issue_date' => 'date',
        'due_date' => 'date',
    ];
}
