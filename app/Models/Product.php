<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'mf_id',
        'sku',
        'category_id',
        'seq',
        'name',
        'unit',
        'price',
        'quantity',
        'cost',
        'tax_category',
        'is_deduct_withholding_tax',
        'is_active',
        'description',
        'attributes',
        'mf_updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'attributes' => 'array',
        'is_deduct_withholding_tax' => 'boolean',
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'mf_updated_at' => 'datetime',
    ];
}
