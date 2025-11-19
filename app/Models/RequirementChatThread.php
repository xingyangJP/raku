<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequirementChatThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'estimate_id',
        'user_id',
    ];

    public function messages()
    {
        return $this->hasMany(RequirementChatMessage::class, 'thread_id')->orderBy('created_at');
    }

    public function estimate()
    {
        return $this->belongsTo(Estimate::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

