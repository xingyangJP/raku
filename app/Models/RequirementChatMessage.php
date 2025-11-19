<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequirementChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'thread_id',
        'role',
        'content',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function thread()
    {
        return $this->belongsTo(RequirementChatThread::class, 'thread_id');
    }
}

