<?php

namespace App\Services;

use App\Models\Estimate;
use App\Models\Product;
use Illuminate\Support\Collection;

class EstimateMetricsService
{
    private const FIRST_BUSINESS_KEY = 'first_business';

    public function buildMetrics(Estimate $estimate, ?array $productLookups = null): array
    {
        $lookups = $productLookups ?? $this->buildProductLookups();
        $subtotal = $this->calculateSubtotalExcludingTax($estimate);
        $developmentSubtotal = $this->calculateDevelopmentSubtotalExcludingTax($estimate, $lookups);
        $firstBusinessSubtotal = $this->calculateFirstBusinessSubtotalExcludingTax($estimate, $lookups);
        $taxAmount = $this->numberOrZero($estimate->tax_amount);
        $totalAmount = $this->numberOrZero($estimate->total_amount);

        return [
            'subtotal_excluding_tax' => $subtotal,
            'sales_subtotal_excluding_tax' => $subtotal,
            'development_subtotal_excluding_tax' => $developmentSubtotal,
            'first_business_subtotal_excluding_tax' => $firstBusinessSubtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'effort_person_days' => $this->calculateEffort($estimate, $lookups),
        ];
    }

    public function calculateSubtotalExcludingTax(Estimate $estimate): float
    {
        if (is_numeric($estimate->total_amount) || is_numeric($estimate->tax_amount)) {
            return round($this->numberOrZero($estimate->total_amount) - $this->numberOrZero($estimate->tax_amount), 1);
        }

        return round($this->calculateItemsSubtotal($estimate->items ?? []), 1);
    }

    public function calculateDevelopmentSubtotalExcludingTax(Estimate $estimate, ?array $productLookups = null): float
    {
        return $this->calculateItemsSubtotalByBusinessDivision($estimate, $productLookups, includeFirstBusiness: false);
    }

    public function calculateFirstBusinessSubtotalExcludingTax(Estimate $estimate, ?array $productLookups = null): float
    {
        return $this->calculateItemsSubtotalByBusinessDivision($estimate, $productLookups, includeFirstBusiness: true);
    }

    public function calculateEffort(Estimate $estimate, ?array $productLookups = null): float
    {
        $lookups = $productLookups ?? $this->buildProductLookups();
        $items = is_array($estimate->items) ? $estimate->items : [];
        $defaultCapacityPerPersonDays = (float) config('app.person_days_per_person_month', 20);
        $personHoursPerPersonDay = (float) config('app.person_hours_per_person_day', 8);

        return round(collect($items)->sum(function ($item) use ($lookups, $defaultCapacityPerPersonDays, $personHoursPerPersonDay) {
            $item = is_array($item) ? $item : [];
            $product = $this->resolveProduct($item, $lookups);
            $businessDivision = $this->resolveBusinessDivision($item, $product);

            if ($businessDivision === self::FIRST_BUSINESS_KEY) {
                return 0;
            }

            $qty = $this->numberOrZero($item['qty'] ?? $item['quantity'] ?? 0);
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

    public function buildProductLookups(?Collection $products = null): array
    {
        $products ??= Product::query()
            ->where('is_active', true)
            ->get(['id', 'name', 'sku', 'unit', 'business_division']);

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

    public function resolveProduct(array $item, array $productLookups): ?Product
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

    private function calculateItemsSubtotal(array $items): float
    {
        return collect($items)->sum(function ($item) {
            $item = is_array($item) ? $item : [];

            if (is_numeric($item['total_price'] ?? null)) {
                return (float) $item['total_price'];
            }
            if (is_numeric($item['amount'] ?? null)) {
                return (float) $item['amount'];
            }

            return $this->numberOrZero($item['qty'] ?? $item['quantity'] ?? 0)
                * $this->numberOrZero($item['price'] ?? $item['unit_price'] ?? 0);
        });
    }

    private function calculateItemsSubtotalByBusinessDivision(Estimate $estimate, ?array $productLookups, bool $includeFirstBusiness): float
    {
        $lookups = $productLookups ?? $this->buildProductLookups();
        $items = is_array($estimate->items) ? $estimate->items : [];

        return round(collect($items)->sum(function ($item) use ($lookups, $includeFirstBusiness) {
            $item = is_array($item) ? $item : [];
            $product = $this->resolveProduct($item, $lookups);
            $isFirstBusiness = $this->resolveBusinessDivision($item, $product) === self::FIRST_BUSINESS_KEY;

            if ($isFirstBusiness !== $includeFirstBusiness) {
                return 0;
            }

            return $this->calculateItemsSubtotal([$item]);
        }), 1);
    }

    private function resolveBusinessDivision(array $item, ?Product $product): ?string
    {
        return $item['business_division'] ?? ($product->business_division ?? null);
    }

    private function numberOrZero(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
