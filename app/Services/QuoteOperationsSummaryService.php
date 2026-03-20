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

    public function summarize(string $timezone = 'Asia/Tokyo'): array
    {
        $companySetting = CompanySetting::current();
        $monthlyCapacity = (float) $companySetting->resolveMonthlyCapacityPersonDays();
        $currentMonth = Carbon::now($timezone)->startOfMonth();
        $nextMonth = $currentMonth->copy()->addMonth();

        $products = Product::query()
            ->where('is_active', true)
            ->get(['id', 'name', 'sku', 'business_division']);

        $productLookups = $this->buildProductLookups($products);

        $estimates = Estimate::query()
            ->whereNull('mf_deleted_at')
            ->get(['id', 'items', 'issue_date', 'due_date', 'delivery_date', 'is_order_confirmed']);

        return [
            'staff_count' => $companySetting->resolveOperationalStaffCount(),
            'monthly_capacity_person_days' => $monthlyCapacity,
            'current_month' => $this->buildMonthSummary($estimates, $productLookups, $currentMonth, $monthlyCapacity),
            'next_month' => $this->buildMonthSummary($estimates, $productLookups, $nextMonth, $monthlyCapacity),
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

    private function buildMonthSummary(Collection $estimates, array $productLookups, Carbon $month, float $monthlyCapacity): array
    {
        $confirmed = $estimates->filter(function (Estimate $estimate) use ($month) {
            if (!$estimate->is_order_confirmed) {
                return false;
            }

            $scheduledMonth = $this->resolveScheduledMonth($estimate);
            return $scheduledMonth && $scheduledMonth->isSameMonth($month);
        });

        $plannedPersonDays = round($confirmed->sum(fn (Estimate $estimate) => $this->calculateEffort($estimate, $productLookups)), 1);
        $availablePersonDays = round(max(0, $monthlyCapacity - $plannedPersonDays), 1);
        $utilization = $monthlyCapacity > 0
            ? round(($plannedPersonDays / $monthlyCapacity) * 100, 1)
            : 0.0;

        return [
            'month' => $month->format('Y-m'),
            'label' => $month->format('Y年n月'),
            'planned_person_days' => $plannedPersonDays,
            'available_person_days' => $availablePersonDays,
            'utilization_rate' => $utilization,
            'confirmed_count' => $confirmed->count(),
            'status' => $this->resolveStatus($utilization),
        ];
    }

    private function resolveScheduledMonth(Estimate $estimate): ?Carbon
    {
        foreach (['delivery_date', 'due_date', 'issue_date'] as $field) {
            $value = $estimate->{$field};
            if (!$value) {
                continue;
            }

            return $value instanceof Carbon
                ? $value->copy()->startOfMonth()
                : Carbon::parse($value)->startOfMonth();
        }

        return null;
    }

    private function calculateEffort(Estimate $estimate, array $productLookups): float
    {
        $items = is_array($estimate->items) ? $estimate->items : [];

        return round(collect($items)->sum(function ($item) use ($productLookups) {
            $product = $this->resolveProduct($item, $productLookups);
            if (($product->business_division ?? null) === self::FIRST_BUSINESS_KEY) {
                return 0;
            }

            $quantity = $item['qty'] ?? $item['quantity'] ?? 0;
            return is_numeric($quantity) ? (float) $quantity : 0;
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
