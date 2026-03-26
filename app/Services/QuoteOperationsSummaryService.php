<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Estimate;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class QuoteOperationsSummaryService
{
    private const FIRST_BUSINESS_KEY = 'first_business';

    public function __construct(private readonly EstimateEffortAllocationService $effortAllocation)
    {
    }

    public function summarize(string $timezone = 'Asia/Tokyo'): array
    {
        $companySetting = CompanySetting::current();
        $monthlyCapacity = (float) $companySetting->resolveMonthlyCapacityPersonDays();
        $defaultCapacityPerPersonDays = (float) $companySetting->resolveDefaultCapacityPerPersonDays();
        $personHoursPerPersonDay = (float) config('app.person_hours_per_person_day', 8);
        $currentMonth = Carbon::now($timezone)->startOfMonth();
        $nextMonth = $currentMonth->copy()->addMonth();

        $products = Product::query()
            ->where('is_active', true)
            ->get(['id', 'name', 'sku', 'business_division']);

        $productLookups = $this->buildProductLookups($products);

        $estimates = Estimate::query()
            ->whereNull('mf_deleted_at')
            ->get(['id', 'items', 'issue_date', 'due_date', 'start_date', 'delivery_date', 'is_order_confirmed']);

        return [
            'staff_count' => $companySetting->resolveOperationalStaffCount(),
            'monthly_capacity_person_days' => $monthlyCapacity,
            'default_capacity_person_days' => $defaultCapacityPerPersonDays,
            'person_hours_per_person_day' => $personHoursPerPersonDay,
            'current_month' => $this->buildMonthSummary($estimates, $productLookups, $currentMonth, $monthlyCapacity, $defaultCapacityPerPersonDays, $personHoursPerPersonDay, $timezone),
            'next_month' => $this->buildMonthSummary($estimates, $productLookups, $nextMonth, $monthlyCapacity, $defaultCapacityPerPersonDays, $personHoursPerPersonDay, $timezone),
        ];
    }

    private function buildProductLookups(Collection $products): array
    {
        $idMap = [];
        $skuMap = [];
        $nameMap = [];

        foreach ($products as $product) {
            if ($product->id !== null) {
                $idMap[(int) $product->id] = $product;
            }

            $sku = mb_strtolower(trim((string) ($product->sku ?? '')));
            if ($sku !== '') {
                $skuMap[$sku] = $product;
            }

            $name = mb_strtolower(trim((string) ($product->name ?? '')));
            if ($name !== '') {
                $nameMap[$name] = $product;
            }
        }

        return [
            'id' => $idMap,
            'sku' => $skuMap,
            'name' => $nameMap,
        ];
    }

    private function buildMonthSummary(
        Collection $estimates,
        array $productLookups,
        Carbon $month,
        float $monthlyCapacity,
        float $defaultCapacityPerPersonDays,
        float $personHoursPerPersonDay,
        string $timezone
    ): array
    {
        $monthKey = $month->copy()->startOfMonth()->toDateString();
        $confirmed = $estimates->filter(function (Estimate $estimate) use ($month) {
            if (!$estimate->is_order_confirmed) {
                return false;
            }

            return true;
        });

        $confirmed = $confirmed->filter(fn (Estimate $estimate) => array_key_exists($monthKey, $this->effortAllocation->resolveMonthlyRatios($estimate, $timezone)));

        $plannedPersonDays = round($confirmed->sum(function (Estimate $estimate) use ($productLookups, $defaultCapacityPerPersonDays, $personHoursPerPersonDay, $timezone, $monthKey) {
            $totalEffort = $this->calculateEffort($estimate, $productLookups, $defaultCapacityPerPersonDays, $personHoursPerPersonDay);

            return (float) ($this->effortAllocation->resolveMonthlyEffort($estimate, $totalEffort, $timezone)[$monthKey] ?? 0);
        }), 1);
        $availablePersonDays = round($monthlyCapacity - $plannedPersonDays, 1);
        $utilization = $monthlyCapacity > 0
            ? round(($plannedPersonDays / $monthlyCapacity) * 100, 1)
            : 0.0;

        return [
            'month' => $month->format('Y-m'),
            'label' => $month->format('Y年n月'),
            'capacity_person_days' => round($monthlyCapacity, 1),
            'planned_person_days' => $plannedPersonDays,
            'available_person_days' => $availablePersonDays,
            'utilization_rate' => $utilization,
            'confirmed_count' => $confirmed->count(),
            'status' => $this->resolveStatus($utilization),
        ];
    }

    private function calculateEffort(
        Estimate $estimate,
        array $productLookups,
        float $defaultCapacityPerPersonDays,
        float $personHoursPerPersonDay
    ): float
    {
        $items = is_array($estimate->items) ? $estimate->items : [];

        return round(collect($items)->sum(function ($item) use ($productLookups, $defaultCapacityPerPersonDays, $personHoursPerPersonDay) {
            $product = $this->resolveProduct($item, $productLookups);
            if (($product->business_division ?? null) === self::FIRST_BUSINESS_KEY) {
                return 0;
            }

            $quantity = $item['qty'] ?? $item['quantity'] ?? 0;
            $qty = is_numeric($quantity) ? (float) $quantity : 0.0;
            if ($qty <= 0) {
                return 0;
            }

            $unit = mb_strtolower(trim((string) ($item['unit'] ?? ($product->unit ?? ''))));
            if ($unit === '' || str_contains($unit, '人日')) {
                return $qty;
            }
            if (str_contains($unit, '人月')) {
                return $qty * $defaultCapacityPerPersonDays;
            }
            if (str_contains($unit, '人時') || str_contains($unit, '時間') || $unit === 'h' || $unit === 'hr') {
                return $personHoursPerPersonDay > 0 ? ($qty / $personHoursPerPersonDay) : 0;
            }

            return 0;
        }), 1);
    }

    private function resolveProduct(array $item, array $productLookups): ?Product
    {
        $productId = $item['product_id'] ?? $item['productId'] ?? data_get($item, 'product.id');
        if ($productId !== null && $productId !== '') {
            $product = $productLookups['id'][(int) $productId] ?? null;
            if ($product) {
                return $product;
            }
        }

        $sku = mb_strtolower(trim((string) ($item['code'] ?? $item['product_code'] ?? $item['sku'] ?? '')));
        if ($sku !== '') {
            $product = $productLookups['sku'][$sku] ?? null;
            if ($product) {
                return $product;
            }
        }

        $name = mb_strtolower(trim((string) ($item['name'] ?? $item['product_name'] ?? '')));
        if ($name !== '') {
            return $productLookups['name'][$name] ?? null;
        }

        return null;
    }

    private function resolveStatus(float $utilization): string
    {
        return match (true) {
            $utilization >= 100 => 'danger',
            $utilization >= 85 => 'warning',
            default => 'ok',
        };
    }
}
