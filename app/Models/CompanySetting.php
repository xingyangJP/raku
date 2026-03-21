<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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
        $userCapacityRows = $this->resolveUsersCapacityRows();
        if ($userCapacityRows->isNotEmpty()) {
            return max(1, $userCapacityRows->filter(fn (array $row) => (float) ($row['resolved_capacity_person_days'] ?? 0) > 0)->count());
        }

        return max(1, (int) ($this->operational_staff_count ?: static::defaultStaffCount()));
    }

    public function resolveMonthlyCapacityPersonDays(): float
    {
        $userCapacityRows = $this->resolveUsersCapacityRows();
        if ($userCapacityRows->isNotEmpty()) {
            return round((float) $userCapacityRows->sum(fn (array $row) => (float) ($row['resolved_capacity_person_days'] ?? 0)), 1);
        }

        $personDaysPerMonth = $this->resolveDefaultCapacityPerPersonDays();

        return $this->resolveOperationalStaffCount() * $personDaysPerMonth;
    }

    public function resolveDefaultCapacityPerPersonDays(): float
    {
        return (float) config('app.person_days_per_person_month', 20);
    }

    public function resolveUsersCapacityRows(): Collection
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'work_capacity_person_days')) {
            return collect();
        }

        $fallback = $this->resolveDefaultCapacityPerPersonDays();

        return User::query()
            ->visibleForBusiness()
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'external_user_id', 'work_capacity_person_days'])
            ->map(function (User $user) use ($fallback) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'external_user_id' => $user->external_user_id,
                    'work_capacity_person_days' => $user->work_capacity_person_days,
                    'resolved_capacity_person_days' => $user->resolveWorkCapacityPersonDays($fallback),
                    'is_capacity_configured' => $user->work_capacity_person_days !== null,
                ];
            })
            ->values();
    }

    public function resolveUserCapacityMap(): array
    {
        $rows = $this->resolveUsersCapacityRows();

        return [
            'rows' => $rows->all(),
            'by_id' => $rows
                ->filter(fn (array $row) => !empty($row['id']))
                ->mapWithKeys(fn (array $row) => [(string) $row['id'] => (float) $row['resolved_capacity_person_days']])
                ->all(),
            'by_name' => $rows
                ->filter(fn (array $row) => trim((string) ($row['name'] ?? '')) !== '')
                ->mapWithKeys(fn (array $row) => [trim((string) $row['name']) => (float) $row['resolved_capacity_person_days']])
                ->all(),
            'configured_by_id' => $rows
                ->filter(fn (array $row) => ($row['is_capacity_configured'] ?? false) && !empty($row['id']))
                ->mapWithKeys(fn (array $row) => [(string) $row['id'] => (float) $row['resolved_capacity_person_days']])
                ->all(),
        ];
    }
}
