<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingItem extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'billing_id',
        'name',
        'code',
        'detail',
        'unit',
        'price',
        'quantity',
        'is_deduct_withholding_tax',
        'excise',
        'delivery_number',
        'delivery_date',
    ];

    protected $casts = [
        'is_deduct_withholding_tax' => 'boolean',
        'delivery_date' => 'date',
        'price' => 'decimal:2',
        'quantity' => 'decimal:2',
    ];

    /**
     * Get the billing that owns the item.
     */
    public function billing(): BelongsTo
    {
        return $this->belongsTo(Billing::class);
    }
}

