<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesAiCoachSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'goal',
        'context',
        'questions',
        'fallback',
        'message',
    ];

    protected $casts = [
        'questions' => 'array',
        'fallback' => 'boolean',
    ];
}
