<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CompanySetting extends Model
{
    protected $fillable = [
        'company_name',
        'address',
        'phone',
        'email',
        'logo_path',
        'seal_path',
        'fiscal_year_start_month',
        'monthly_close_day',
        'post_close_lock_policy',
        'default_tax_rate',
        'tax_category_default',
        'calc_order',
        'rounding_subtotal',
        'rounding_tax',
        'rounding_total',
        'unit_price_precision',
        'currency',
        'estimate_number_format',
        'draft_estimate_number_format',
        'sequence_reset_rule',
        'operational_staff_count',
    ];

    protected $casts = [
        'default_tax_rate' => 'float',
        'operational_staff_count' => 'integer',
    ];

    public static function current(): self
    {
        if (!Schema::hasTable('company_settings')) {
            return new static([
                'company_name' => (string) config('app.name', 'KCS販売管理'),
                'operational_staff_count' => static::defaultStaffCount(),
            ]);
        }

        return static::query()->firstOrCreate(
            ['id' => 1],
            [
                'company_name' => (string) config('app.name', 'KCS販売管理'),
                'operational_staff_count' => static::defaultStaffCount(),
            ]
        );
    }

    public static function defaultStaffCount(): int
    {
        $personDaysPerMonth = (float) config('app.person_days_per_person_month', 20);
        $configuredCapacity = (float) config('app.monthly_capacity_person_days', 80);

        if ($personDaysPerMonth <= 0) {
            return 1;
        }

        return max(1, (int) round($configuredCapacity / $personDaysPerMonth));
    }

    public function resolveOperationalStaffCount(): int
    {
        return max(1, (int) ($this->operational_staff_count ?: static::defaultStaffCount()));
    }

    public function resolveMonthlyCapacityPersonDays(): float
    {
        $personDaysPerMonth = (float) config('app.person_days_per_person_month', 20);

        return $this->resolveOperationalStaffCount() * $personDaysPerMonth;
    }
}
