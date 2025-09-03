<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Billing extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'pdf_url',
        'operator_id',
        'department_id',
        'member_id',
        'member_name',
        'partner_id',
        'partner_name',
        'office_id',
        'office_name',
        'office_detail',
        'title',
        'memo',
        'payment_condition',
        'billing_date',
        'due_date',
        'sales_date',
        'billing_number',
        'note',
        'document_name',
        'payment_status',
        'email_status',
        'posting_status',
        'is_downloaded',
        'is_locked',
        'deduct_price',
        'tag_names',
        'excise_price',
        'excise_price_of_untaxable',
        'excise_price_of_non_taxable',
        'excise_price_of_tax_exemption',
        'excise_price_of_five_percent',
        'excise_price_of_eight_percent',
        'excise_price_of_eight_percent_as_reduced_tax_rate',
        'excise_price_of_ten_percent',
        'subtotal_price',
        'subtotal_of_untaxable_excise',
        'subtotal_of_non_taxable_excise',
        'subtotal_of_tax_exemption_excise',
        'subtotal_of_five_percent_excise',
        'subtotal_of_eight_percent_excise',
        'subtotal_of_eight_percent_as_reduced_tax_rate_excise',
        'subtotal_of_ten_percent_excise',
        'subtotal_with_tax_of_untaxable_excise',
        'subtotal_with_tax_of_non_taxable_excise',
        'subtotal_with_tax_of_tax_exemption_excise',
        'subtotal_with_tax_of_five_percent_excise',
        'subtotal_with_tax_of_eight_percent_excise',
        'subtotal_with_tax_of_eight_percent_as_reduced_tax_rate_excise',
        'subtotal_with_tax_of_ten_percent_excise',
        'total_price',
        'registration_code',
        'use_invoice_template',
        'config',
    ];

    protected $casts = [
        'billing_date' => 'date',
        'due_date' => 'date',
        'sales_date' => 'date',
        'is_downloaded' => 'boolean',
        'is_locked' => 'boolean',
        'tag_names' => 'array',
        'use_invoice_template' => 'boolean',
        'config' => 'array',
        'deduct_price' => 'decimal:2',
        'excise_price' => 'decimal:2',
        'excise_price_of_untaxable' => 'decimal:2',
        'excise_price_of_non_taxable' => 'decimal:2',
        'excise_price_of_tax_exemption' => 'decimal:2',
        'excise_price_of_five_percent' => 'decimal:2',
        'excise_price_of_eight_percent' => 'decimal:2',
        'excise_price_of_eight_percent_as_reduced_tax_rate' => 'decimal:2',
        'excise_price_of_ten_percent' => 'decimal:2',
        'subtotal_price' => 'decimal:2',
        'subtotal_of_untaxable_excise' => 'decimal:2',
        'subtotal_of_non_taxable_excise' => 'decimal:2',
        'subtotal_of_tax_exemption_excise' => 'decimal:2',
        'subtotal_of_five_percent_excise' => 'decimal:2',
        'subtotal_of_eight_percent_excise' => 'decimal:2',
        'subtotal_of_eight_percent_as_reduced_tax_rate_excise' => 'decimal:2',
        'subtotal_of_ten_percent_excise' => 'decimal:2',
        'subtotal_with_tax_of_untaxable_excise' => 'decimal:2',
        'subtotal_with_tax_of_non_taxable_excise' => 'decimal:2',
        'subtotal_with_tax_of_tax_exemption_excise' => 'decimal:2',
        'subtotal_with_tax_of_five_percent_excise' => 'decimal:2',
        'subtotal_with_tax_of_eight_percent_excise' => 'decimal:2',
        'subtotal_with_tax_of_eight_percent_as_reduced_tax_rate_excise' => 'decimal:2',
        'subtotal_with_tax_of_ten_percent_excise' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /**
     * Get the items for the billing.
     */
    public function items(): HasMany
    {
        return $this->hasMany(BillingItem::class);
    }
}

