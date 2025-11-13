<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstimateAiLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'estimate_id',
        'action',
        'input_summary',
        'structured_requirements',
        'prompt_payload',
        'ai_response',
    ];

    protected $casts = [
        'structured_requirements' => 'array',
    ];

    public function estimate()
    {
        return $this->belongsTo(Estimate::class);
    }
}
