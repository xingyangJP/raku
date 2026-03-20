<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Estimate;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EstimateWorkloadSimulationService
{
    public function __construct(private readonly EstimateItemAssignmentNormalizer $assignmentNormalizer)
    {
    }

    public function build(?Estimate $excludedEstimate = null, ?string $referenceDate = null): array
    {
        $timezone = config('app.sales_timezone', config('app.timezone', 'Asia/Tokyo'));
        $referenceAt = $referenceDate ? Carbon::parse($referenceDate, $timezone) : Carbon::now($timezone);
        $windowStart = $referenceAt->copy()->subYear()->startOfYear();
        $windowEnd = $referenceAt->copy()->addYear()->endOfYear();
        $monthKeys = collect(CarbonPeriod::create($windowStart, '1 month', $windowEnd))
            ->map(fn ($month) => Carbon::instance($month)->startOfMonth()->toDateString())
            ->values()
            ->all();

        $productLookup = $this->buildProductLookup();
        $capacityPerPersonDays = (float) config('app.person_days_per_person_month', 20.0);
        $staffSetting = CompanySetting::current();

        $query = Estimate::query()
            ->whereNull('mf_deleted_at')
            ->whereNotIn('status', ['rejected', 'lost'])
            ->get([
                'id',
                'status',
                'issue_date',
                'due_date',
                'delivery_date',
                'items',
                'title',
            ]);

        if ($excludedEstimate?->id) {
            $query = $query->reject(fn (Estimate $estimate) => (int) $estimate->id === (int) $excludedEstimate->id)->values();
        }

        $buckets = [];
        foreach ($monthKeys as $monthKey) {
            $buckets[$monthKey] = [
                'rows' => [],
                'unassigned_person_days' => 0.0,
            ];
        }

        foreach ($query as $estimate) {
            $recognizedAt = $this->resolveRecognitionDate($estimate, $timezone);
            if (!$recognizedAt) {
                continue;
            }

            $monthKey = $recognizedAt->copy()->startOfMonth()->toDateString();
            if (!isset($buckets[$monthKey])) {
                continue;
            }

            foreach ((array) ($estimate->items ?? []) as $item) {
                if (!is_array($item) || $this->shouldExcludeEffortItem($item, $productLookup)) {
                    continue;
                }

                $qty = (float) ($item['qty'] ?? $item['quantity'] ?? 1);
                if ($qty === 0.0) {
                    $qty = 1.0;
                }

                $personDays = $this->toPersonDays($qty, (string) ($item['unit'] ?? ''));
                if ($personDays <= 0.0) {
                    continue;
                }

                $assignees = $this->assignmentNormalizer->normalizeAssignees((array) ($item['assignees'] ?? []));
                if (empty($assignees)) {
                    $buckets[$monthKey]['unassigned_person_days'] += $personDays;
                    continue;
                }

                foreach ($assignees as $assignee) {
                    $sharePercent = (float) ($assignee['share_percent'] ?? 0);
                    if ($sharePercent <= 0) {
                        continue;
                    }

                    $assignedPersonDays = $personDays * ($sharePercent / 100);
                    if ($assignedPersonDays <= 0) {
                        continue;
                    }

                    $key = trim((string) ($assignee['user_id'] ?? ''));
                    if ($key === '') {
                        $key = trim((string) ($assignee['user_name'] ?? ''));
                    }
                    if ($key === '') {
                        $key = '未設定担当';
                    }

                    $displayName = trim((string) ($assignee['user_name'] ?? ''));
                    if ($displayName === '') {
                        $displayName = $key;
                    }

                    if (!isset($buckets[$monthKey]['rows'][$key])) {
                        $buckets[$monthKey]['rows'][$key] = [
                            'user_key' => $key,
                            'name' => $displayName,
                            'planned_person_days' => 0.0,
                            'estimate_ids' => [],
                            'latest_titles' => [],
                        ];
                    }

                    $buckets[$monthKey]['rows'][$key]['planned_person_days'] += $assignedPersonDays;
                    $buckets[$monthKey]['rows'][$key]['estimate_ids'][(string) $estimate->id] = true;
                    $title = trim((string) ($estimate->title ?? $item['name'] ?? ''));
                    if ($title !== '' && count($buckets[$monthKey]['rows'][$key]['latest_titles']) < 3) {
                        $buckets[$monthKey]['rows'][$key]['latest_titles'][$title] = $title;
                    }
                }
            }
        }

        $months = [];
        foreach ($buckets as $monthKey => $bucket) {
            $rows = collect($bucket['rows'])
                ->map(function (array $row) use ($capacityPerPersonDays) {
                    $plannedPersonDays = round((float) ($row['planned_person_days'] ?? 0), 1);
                    $availablePersonDays = round($capacityPerPersonDays - $plannedPersonDays, 1);

                    return [
                        'user_key' => (string) ($row['user_key'] ?? $row['name'] ?? ''),
                        'name' => (string) ($row['name'] ?? '未設定担当'),
                        'planned_person_days' => $plannedPersonDays,
                        'available_person_days' => $availablePersonDays,
                        'utilization_rate' => round($this->calculateRate($plannedPersonDays, $capacityPerPersonDays), 1),
                        'estimate_count' => count($row['estimate_ids'] ?? []),
                        'latest_titles' => array_values($row['latest_titles'] ?? []),
                    ];
                })
                ->sortByDesc('planned_person_days')
                ->values()
                ->all();

            $months[] = [
                'month_key' => Carbon::parse($monthKey, $timezone)->format('Y-m'),
                'label' => Carbon::parse($monthKey, $timezone)->format('Y年n月'),
                'rows' => $rows,
                'summary' => [
                    'tracked_people_count' => count($rows),
                    'planned_person_days' => round((float) collect($rows)->sum('planned_person_days'), 1),
                    'unassigned_person_days' => round((float) ($bucket['unassigned_person_days'] ?? 0), 1),
                    'high_load_count' => collect($rows)->filter(fn (array $row) => ($row['utilization_rate'] ?? 0) >= 85)->count(),
                    'over_capacity_count' => collect($rows)->filter(fn (array $row) => ($row['utilization_rate'] ?? 0) > 100)->count(),
                ],
            ];
        }

        return [
            'basis' => [
                'label' => '納期月ベース',
                'detail' => '既存案件の納期月ごとの担当者予定工数に、この見積の担当者按分を重ねてシミュレーションします。失注・却下案件は除外します。',
            ],
            'reference_year' => (int) $referenceAt->year,
            'staff_count' => $staffSetting->resolveOperationalStaffCount(),
            'capacity_per_person_days' => round($capacityPerPersonDays, 1),
            'months' => $months,
        ];
    }

    private function resolveRecognitionDate(Estimate $estimate, string $timezone): ?Carbon
    {
        $recognizedAt = $estimate->delivery_date
            ?? $estimate->due_date
            ?? $estimate->issue_date;

        if (!$recognizedAt) {
            return null;
        }

        return Carbon::parse($recognizedAt, $timezone);
    }

    private function shouldExcludeEffortItem(array $item, array $productLookup): bool
    {
        $product = $this->resolveProductForItem($item, $productLookup);
        if ($product && (($product['business_division'] ?? null) === 'first_business')) {
            return true;
        }

        $unit = mb_strtolower((string) ($item['unit'] ?? ''));

        return !($unit === '' || str_contains($unit, '人日') || str_contains($unit, '人月') || str_contains($unit, '人時') || str_contains($unit, '時間') || $unit === 'h' || $unit === 'hr');
    }

    private function resolveProductForItem(array $item, array $productLookup): ?array
    {
        $productId = $item['product_id'] ?? $item['productId'] ?? null;
        if ($productId !== null && isset($productLookup['by_id'][(int) $productId])) {
            return $productLookup['by_id'][(int) $productId];
        }

        $sku = mb_strtolower(trim((string) ($item['code'] ?? $item['product_code'] ?? $item['sku'] ?? '')));
        if ($sku !== '' && isset($productLookup['by_sku'][$sku])) {
            return $productLookup['by_sku'][$sku];
        }

        $name = mb_strtolower(trim((string) ($item['name'] ?? $item['product_name'] ?? '')));
        if ($name !== '' && isset($productLookup['by_name'][$name])) {
            return $productLookup['by_name'][$name];
        }

        return null;
    }

    private function buildProductLookup(): array
    {
        if (!Schema::hasTable('products')) {
            return ['by_id' => [], 'by_sku' => [], 'by_name' => []];
        }

        $rows = DB::table('products')
            ->select(['id', 'sku', 'name', 'business_division'])
            ->get();

        $byId = [];
        $bySku = [];
        $byName = [];

        foreach ($rows as $row) {
            $payload = [
                'id' => (int) $row->id,
                'sku' => (string) ($row->sku ?? ''),
                'name' => (string) ($row->name ?? ''),
                'business_division' => (string) ($row->business_division ?? ''),
            ];

            $byId[(int) $row->id] = $payload;

            $sku = mb_strtolower(trim((string) ($row->sku ?? '')));
            if ($sku !== '') {
                $bySku[$sku] = $payload;
            }

            $name = mb_strtolower(trim((string) ($row->name ?? '')));
            if ($name !== '') {
                $byName[$name] = $payload;
            }
        }

        return ['by_id' => $byId, 'by_sku' => $bySku, 'by_name' => $byName];
    }

    private function toPersonDays(float $value, string $unit): float
    {
        $normalizedUnit = mb_strtolower(trim($unit));
        $personDaysPerPersonMonth = (float) config('app.person_days_per_person_month', 20.0);
        $hoursPerPersonDay = (float) config('app.person_hours_per_person_day', 8.0);

        if ($normalizedUnit !== '' && str_contains($normalizedUnit, '人月')) {
            return $value * $personDaysPerPersonMonth;
        }

        if (
            $normalizedUnit !== ''
            && (str_contains($normalizedUnit, '人時') || str_contains($normalizedUnit, '時間') || $normalizedUnit === 'h' || $normalizedUnit === 'hr')
        ) {
            return $hoursPerPersonDay > 0 ? ($value / $hoursPerPersonDay) : 0.0;
        }

        return $value;
    }

    private function calculateRate(float $numerator, float $denominator): float
    {
        if ($denominator === 0.0) {
            return 0.0;
        }

        return ($numerator / $denominator) * 100;
    }
}
