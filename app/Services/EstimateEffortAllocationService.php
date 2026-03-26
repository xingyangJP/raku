<?php

namespace App\Services;

use App\Models\Estimate;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class EstimateEffortAllocationService
{
    public function resolveMonthlyEffort(
        Estimate $estimate,
        float $totalEffort,
        string $timezone,
        bool $allowFallbackWithoutDelivery = true
    ): array {
        $monthKeys = $this->resolveMonthKeys($estimate, $timezone, $allowFallbackWithoutDelivery);
        if ($monthKeys === [] || $totalEffort <= 0) {
            return [];
        }

        $allocations = [];
        $remaining = $totalEffort;
        $lastIndex = count($monthKeys) - 1;

        foreach ($monthKeys as $index => $monthKey) {
            if ($index === $lastIndex) {
                $allocations[$monthKey] = $remaining;
                break;
            }

            $share = round($totalEffort / count($monthKeys), 4);
            $allocations[$monthKey] = $share;
            $remaining -= $share;
        }

        return $allocations;
    }

    public function resolveMonthlyRatios(
        Estimate $estimate,
        string $timezone,
        bool $allowFallbackWithoutDelivery = true
    ): array {
        $monthKeys = $this->resolveMonthKeys($estimate, $timezone, $allowFallbackWithoutDelivery);
        if ($monthKeys === []) {
            return [];
        }

        $ratio = 1 / count($monthKeys);

        return collect($monthKeys)
            ->mapWithKeys(fn (string $monthKey) => [$monthKey => $ratio])
            ->all();
    }

    private function resolveMonthKeys(Estimate $estimate, string $timezone, bool $allowFallbackWithoutDelivery): array
    {
        if ($estimate->delivery_date) {
            $deliveryAt = $this->toMonthStart($estimate->delivery_date, $timezone);
            $startAt = $estimate->start_date ? $this->toMonthStart($estimate->start_date, $timezone) : null;

            if ($startAt && $startAt->lte($deliveryAt)) {
                return collect(CarbonPeriod::create($startAt, '1 month', $deliveryAt))
                    ->map(fn ($month) => Carbon::instance($month)->startOfMonth()->toDateString())
                    ->values()
                    ->all();
            }

            return [$deliveryAt->toDateString()];
        }

        if (!$allowFallbackWithoutDelivery) {
            return [];
        }

        foreach (['due_date', 'issue_date'] as $field) {
            if (!$estimate->{$field}) {
                continue;
            }

            return [$this->toMonthStart($estimate->{$field}, $timezone)->toDateString()];
        }

        return [];
    }

    private function toMonthStart(Carbon|string $value, string $timezone): Carbon
    {
        return $value instanceof Carbon
            ? $value->copy()->setTimezone($timezone)->startOfMonth()
            : Carbon::parse($value, $timezone)->startOfMonth();
    }
}
