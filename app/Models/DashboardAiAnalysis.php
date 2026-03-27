<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DashboardAiAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'analysis_date',
        'target_year',
        'target_month',
        'section_key',
        'status',
        'model',
        'analysis_items',
        'analysis_overview',
        'prompt_payload',
        'response_payload',
        'error_message',
        'generated_at',
    ];

    protected $casts = [
        'analysis_date' => 'date',
        'analysis_items' => 'array',
        'analysis_overview' => 'array',
        'generated_at' => 'datetime',
    ];
}
