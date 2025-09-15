<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $fillable = [
        'mf_partner_id',
        'code',
        'name',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}

