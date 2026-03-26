<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\CompanySetting;
use App\Models\Estimate;
use App\Models\MaintenanceFeeSnapshot;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ManagementMetricsService
{
    public function __construct(private readonly EstimateEffortAllocationService $effortAllocation)
    {
    }

    public function build(?int $selectedYear = null, ?int $selectedMonth = null): array
    {
        $timezone = config('app.sales_timezone', config('app.timezone', 'Asia/Tokyo'));
        $now = Carbon::now($timezone);
        $year = ($selectedYear && $selectedYear >= 2000 && $selectedYear <= 2100) ? $selectedYear : $now->year;
        $month = ($selectedMonth && $selectedMonth >= 1 && $selectedMonth <= 12) ? $selectedMonth : $now->month;

        $currentStart = Carbon::create($year, $month, 1, 0, 0, 0, $timezone)->startOfMonth();
        $currentEnd = $currentStart->copy()->endOfMonth();
        $previousStart = $currentStart->copy()->subMonthNoOverflow()->startOfMonth();
        $previousEnd = $previousStart->copy()->endOfMonth();
        $currentYearStart = $currentStart->copy()->startOfYear();
        $currentYearEnd = $currentStart->copy()->endOfYear();
        $previousYearStart = $currentYearStart->copy()->subYear();
        $previousYearEnd = $currentYearEnd->copy()->subYear();
        $previousYearCurrentStart = $currentStart->copy()->subYear()->startOfMonth();
        $horizonStart = $previousYearStart->copy();
        $horizonEnd = $currentYearEnd->copy();

        $monthKeys = collect(CarbonPeriod::create($horizonStart, '1 month', $horizonEnd))
            ->map(fn ($month) => Carbon::instance($month)->startOfMonth()->toDateString())
            ->values()
            ->all();
        $availableYears = collect([$now->copy()->subYear()->year, $now->year, $currentStart->year])
            ->unique()
            ->sort()
            ->values()
            ->all();
        $availableMonths = collect(range(1, 12))
            ->map(fn (int $monthNumber) => [
                'value' => $monthNumber,
                'label' => "{$monthNumber}月",
            ])
            ->all();

        $productLookup = $this->buildProductLookup();
        $staffSetting = CompanySetting::current();
        $monthlyCapacity = $staffSetting->resolveMonthlyCapacityPersonDays();
        $personDaysPerMonth = $staffSetting->resolveDefaultCapacityPerPersonDays();
        $personHoursPerDay = (float) config('app.person_hours_per_person_day', 8);
        $capacityMap = $staffSetting->resolveUserCapacityMap();

        $sections = [
            'overall' => $this->createSectionMeta('総合', '全社の予実、資金繰り、工数を俯瞰'),
            'development' => $this->createSectionMeta('開発', '第5種事業ベースの開発案件を集計'),
            'sales' => $this->createSectionMeta('仕入れ販売', '第1種事業ベースの販売案件を集計'),
            'maintenance' => $this->createSectionMeta('保守', '保守売上スナップショットを集計'),
        ];

        $monthlyBySection = collect($sections)->mapWithKeys(function (array $meta, string $key) use ($monthKeys, $timezone) {
            return [$key => $this->buildMonthlySkeleton($monthKeys, $timezone)];
        });
        $rankingBuckets = [
            'overall' => ['customers' => [], 'staff' => []],
            'development' => ['customers' => [], 'staff' => []],
            'sales' => ['customers' => [], 'staff' => []],
            'maintenance' => ['customers' => [], 'staff' => []],
        ];
        $peopleBuckets = [
            'overall' => [],
            'development' => [],
            'sales' => [],
            'maintenance' => [],
        ];
        $currentMonthKey = $currentStart->toDateString();

        $effortAssignedTotal = 0.0;
        $effortUnscheduledTotal = 0.0;

        $estimates = Estimate::query()
            ->whereNull('mf_deleted_at')
            ->whereNotIn('status', ['rejected', 'lost'])
            ->get([
                'id',
                'status',
                'issue_date',
                'due_date',
                'start_date',
                'delivery_date',
                'total_amount',
                'items',
                'is_order_confirmed',
                'customer_name',
                'staff_name',
                'title',
            ]);

        foreach ($estimates as $estimate) {
            $recognizedAt = $this->resolveRecognitionDate($estimate, $timezone);
            if (!$recognizedAt) {
                continue;
            }

            $monthKey = $recognizedAt->copy()->startOfMonth()->toDateString();
            if ($monthKey < $horizonStart->toDateString() || $monthKey > $horizonEnd->toDateString()) {
                continue;
            }

            $sectionAmounts = $this->summarizeEstimateBySection($estimate, $productLookup);
            $estimateEffort = (float) ($sectionAmounts['overall']['effort'] ?? 0);
            $effortAllocations = $this->effortAllocation->resolveMonthlyEffort($estimate, $estimateEffort, $timezone, false);

            foreach (['overall', 'development', 'sales'] as $sectionKey) {
                $amounts = $sectionAmounts[$sectionKey] ?? null;
                if (!$amounts) {
                    continue;
                }

                $row = $monthlyBySection[$sectionKey]->get($monthKey);
                if (!$row) {
                    continue;
                }

                $this->applyBudgetToRow($row, $amounts);
                if ($sectionKey === 'overall' && $amounts['sales'] > 0) {
                    $row['budget_count'] += 1;
                } elseif ($sectionKey !== 'overall' && $amounts['sales'] > 0) {
                    $row['budget_count'] += 1;
                }

                if ((bool) $estimate->is_order_confirmed === true) {
                    $this->applyActualToRow($row, $amounts);
                    if ($amounts['sales'] > 0) {
                        $row['actual_count'] += 1;
                    }
                    if ($monthKey === $currentStart->toDateString()) {
                        $this->appendRanking($rankingBuckets[$sectionKey], (string) ($estimate->customer_name ?? '未設定顧客'), (string) ($estimate->staff_name ?? '未設定担当'), (float) ($amounts['sales'] ?? 0), (float) ($amounts['gross_profit'] ?? 0));
                    }
                }

                $monthlyBySection[$sectionKey]->put($monthKey, $row);

                if ($sectionKey !== 'maintenance' && !empty($effortAllocations)) {
                    foreach ($effortAllocations as $effortMonthKey => $allocatedEffort) {
                        if ($allocatedEffort <= 0 || !$monthlyBySection[$sectionKey]->has($effortMonthKey)) {
                            continue;
                        }

                        $deliveryRow = $monthlyBySection[$sectionKey]->get($effortMonthKey);
                        $deliveryRow['budget_effort'] += (float) $allocatedEffort;
                        $monthlyBySection[$sectionKey]->put($effortMonthKey, $deliveryRow);
                    }
                }

                $purchaseAt = $this->resolvePurchaseDate($estimate, $timezone);
                $purchaseMonthKey = $purchaseAt?->copy()->startOfMonth()->toDateString();
                if ($purchaseMonthKey && $monthlyBySection[$sectionKey]->has($purchaseMonthKey)) {
                    $purchaseRow = $monthlyBySection[$sectionKey]->get($purchaseMonthKey);
                    $purchaseRow['budget_purchase_outflow'] += (float) ($amounts['purchase'] ?? 0);
                    if ((bool) $estimate->is_order_confirmed === true) {
                        $purchaseRow['actual_purchase_outflow'] += (float) ($amounts['purchase'] ?? 0);
                    }
                    $monthlyBySection[$sectionKey]->put($purchaseMonthKey, $purchaseRow);
                }

                $collectionAt = $this->resolveCollectionDate($estimate, $timezone);
                $collectionMonthKey = $collectionAt?->copy()->startOfMonth()->toDateString();
                if ($collectionMonthKey && $monthlyBySection[$sectionKey]->has($collectionMonthKey)) {
                    $collectionRow = $monthlyBySection[$sectionKey]->get($collectionMonthKey);
                    $collectionRow['budget_collection_inflow'] += (float) ($amounts['sales'] ?? 0);
                    if ((bool) $estimate->is_order_confirmed === true) {
                        $collectionRow['actual_collection_inflow'] += (float) ($amounts['sales'] ?? 0);
                    }
                    $monthlyBySection[$sectionKey]->put($collectionMonthKey, $collectionRow);
                }
            }

            $currentMonthRatio = $this->effortAllocation->resolveMonthlyRatios($estimate, $timezone)[$currentMonthKey] ?? null;
            if ($currentMonthRatio !== null && $currentMonthRatio > 0) {
                $this->appendPeopleAssignments($peopleBuckets, $estimate, $productLookup, $currentMonthKey, $capacityMap, $personDaysPerMonth, (float) $currentMonthRatio);
            }

            if (!empty($effortAllocations)) {
                $effortAssignedTotal += $estimateEffort;
            } else {
                $effortUnscheduledTotal += $estimateEffort;
            }
        }

        $this->applyMaintenanceSnapshots($monthlyBySection, $rankingBuckets, $horizonStart, $horizonEnd, $currentStart, $timezone);
        $this->applyPaidBillings($monthlyBySection['overall'], $horizonStart, $horizonEnd, $timezone);

        $sectionPayloads = [];
        foreach ($sections as $sectionKey => $meta) {
            $sectionPayloads[$sectionKey] = $this->buildSectionPayload(
                $meta,
                $monthlyBySection[$sectionKey],
                $currentStart,
                $previousStart,
                $previousYearCurrentStart,
                $currentYearStart,
                $currentYearEnd,
                $previousYearStart,
                $previousYearEnd,
                $monthlyCapacity,
                $rankingBuckets[$sectionKey] ?? ['customers' => [], 'staff' => []],
                $peopleBuckets[$sectionKey] ?? [],
                $personDaysPerMonth,
                $capacityMap
            );
        }

        foreach (array_keys($sectionPayloads) as $sectionKey) {
            $sectionPayloads[$sectionKey]['analysis'] = $sectionKey === 'overall'
                ? $this->buildAnalysis($sectionPayloads['overall'], $sectionPayloads['development'], $sectionPayloads['maintenance'])
                : $this->buildSectionAnalysis($sectionKey, $sectionPayloads[$sectionKey]);
        }

        $sectionPayloads['overall']['effort']['summary'] = [
            'assigned_total' => $effortAssignedTotal,
            'unscheduled_total' => $effortUnscheduledTotal,
        ];

        return [
            'basis' => [
                'budget' => '見積書（Estimate）',
                'actual' => '注文書（受注確定済み）',
                'recognition' => '納期ベース',
                'recognition_fallback' => '納期未設定時は見積期限日、さらに未設定時は見積日を使用',
                'effort' => '計画工数（見積ベース）',
                'effort_rule' => '着手日と納期がある案件は開始月から納品月まで均等配賦、着手日未設定は納期月へ一括配賦、納期未設定は未配賦として別管理',
                'maintenance_rule' => '保守は月次スナップショットを売上・粗利同額として計上',
                'cash_rule' => '受注確定案件の回収予測は注文書納期の翌月入金、未受注案件は見積期限日を仮の回収予定として試算',
            ],
            'capacity' => [
                'staff_count' => $staffSetting->resolveOperationalStaffCount(),
                'person_days_per_person_month' => $personDaysPerMonth,
                'person_hours_per_person_day' => $personHoursPerDay,
                'monthly_person_days' => $monthlyCapacity,
                'monthly_person_hours' => $monthlyCapacity * $personHoursPerDay,
            ],
            'filters' => [
                'selected_year' => $currentStart->year,
                'selected_month' => $currentStart->month,
                'available_years' => $availableYears,
                'available_months' => $availableMonths,
            ],
            'periods' => [
                'current' => [
                    'label' => $currentStart->format('Y年n月'),
                    'start' => $currentStart->toDateString(),
                    'end' => $currentEnd->toDateString(),
                ],
                'previous' => [
                    'label' => $previousStart->format('Y年n月'),
                    'start' => $previousStart->toDateString(),
                    'end' => $previousEnd->toDateString(),
                ],
                'previous_year_current' => [
                    'label' => $previousYearCurrentStart->format('Y年n月'),
                    'start' => $previousYearCurrentStart->toDateString(),
                    'end' => $previousYearCurrentStart->copy()->endOfMonth()->toDateString(),
                ],
                'current_year' => [
                    'label' => $currentYearStart->format('Y年'),
                    'start' => $currentYearStart->toDateString(),
                    'end' => $currentYearEnd->toDateString(),
                ],
                'previous_year' => [
                    'label' => $previousYearStart->format('Y年'),
                    'start' => $previousYearStart->toDateString(),
                    'end' => $previousYearEnd->toDateString(),
                ],
            ],
            'sections' => $sectionPayloads,
            'default_section' => 'overall',
            'analysis' => $sectionPayloads['overall']['analysis'] ?? [],
        ];
    }

    private function createSectionMeta(string $label, string $description): array
    {
        return [
            'label' => $label,
            'description' => $description,
        ];
    }

    private function buildMonthlySkeleton(array $monthKeys, string $timezone): Collection
    {
        return collect($monthKeys)->mapWithKeys(function (string $monthKey) use ($timezone) {
            $month = Carbon::parse($monthKey, $timezone);

            return [
                $monthKey => [
                    'month_key' => $monthKey,
                    'month_label' => $month->format('Y年n月'),
                    'budget_sales' => 0.0,
                    'budget_gross_profit' => 0.0,
                    'budget_purchase' => 0.0,
                    'budget_purchase_material' => 0.0,
                    'budget_purchase_labor' => 0.0,
                    'actual_sales' => 0.0,
                    'actual_gross_profit' => 0.0,
                    'actual_purchase' => 0.0,
                    'actual_purchase_material' => 0.0,
                    'actual_purchase_labor' => 0.0,
                    'budget_count' => 0,
                    'actual_count' => 0,
                    'budget_effort' => 0.0,
                    'budget_purchase_outflow' => 0.0,
                    'actual_purchase_outflow' => 0.0,
                    'budget_collection_inflow' => 0.0,
                    'actual_collection_inflow' => 0.0,
                ],
            ];
        });
    }

    private function summarizeEstimateBySection(Estimate $estimate, array $productLookup): array
    {
        $items = is_array($estimate->items) ? $estimate->items : [];
        $defaultLaborCostPerPersonDay = (float) config('app.labor_cost_per_person_day', 0.0);

        $base = [
            'sales' => 0.0,
            'purchase' => 0.0,
            'purchase_material' => 0.0,
            'purchase_labor' => 0.0,
            'gross_profit' => 0.0,
            'effort' => 0.0,
        ];

        $sections = [
            'overall' => $base,
            'development' => $base,
            'sales' => $base,
        ];

        foreach ($items as $item) {
            $qty = (float) (data_get($item, 'qty') ?? data_get($item, 'quantity', 1));
            if ($qty === 0.0) {
                $qty = 1.0;
            }

            $unitPrice = (float) (data_get($item, 'price') ?? data_get($item, 'unit_price', 0));
            $unitCost = (float) (data_get($item, 'cost') ?? data_get($item, 'unit_cost', 0));
            $lineSales = $unitPrice * $qty;
            $lineCost = $unitCost * $qty;
            $personDays = $this->shouldExcludeEffortItem($item, $productLookup)
                ? 0.0
                : $this->toPersonDays($qty, (string) (data_get($item, 'unit') ?? ''));

            if ($lineCost <= 0 && $personDays > 0 && $defaultLaborCostPerPersonDay > 0) {
                $lineCost = $personDays * $defaultLaborCostPerPersonDay;
            }

            $isEffort = !$this->shouldExcludeEffortItem($item, $productLookup);
            $sectionKey = $this->resolveSectionKeyForItem($item, $productLookup);
            $gross = $lineSales - $lineCost;

            $this->applySectionAmount($sections['overall'], $lineSales, $lineCost, $gross, $personDays, $isEffort);

            if ($sectionKey !== null) {
                $this->applySectionAmount($sections[$sectionKey], $lineSales, $lineCost, $gross, $personDays, $isEffort);
            }
        }

        if ($sections['overall']['sales'] <= 0 && (float) ($estimate->total_amount ?? 0) > 0) {
            $sections['overall']['sales'] = (float) $estimate->total_amount;
            $sections['overall']['gross_profit'] = (float) $estimate->total_amount - $sections['overall']['purchase'];
        }

        return $sections;
    }

    private function applySectionAmount(array &$section, float $lineSales, float $lineCost, float $gross, float $personDays, bool $isEffort): void
    {
        $section['sales'] += $lineSales;
        $section['purchase'] += $lineCost;
        $section['gross_profit'] += $gross;
        $section['effort'] += $personDays;

        if ($isEffort) {
            $section['purchase_labor'] += $lineCost;
        } else {
            $section['purchase_material'] += $lineCost;
        }
    }

    private function resolveSectionKeyForItem($item, array $productLookup): ?string
    {
        $product = $this->resolveProductForItem($item, $productLookup);
        $division = (string) (
            data_get($item, 'business_division')
            ?? ($product['business_division'] ?? '')
        );

        if ($division === 'first_business') {
            return 'sales';
        }

        if ($division === '' || $division === 'fifth_business') {
            return 'development';
        }

        return 'development';
    }

    private function applyBudgetToRow(array &$row, array $amounts): void
    {
        $row['budget_sales'] += (float) $amounts['sales'];
        $row['budget_purchase'] += (float) $amounts['purchase'];
        $row['budget_purchase_material'] += (float) $amounts['purchase_material'];
        $row['budget_purchase_labor'] += (float) $amounts['purchase_labor'];
        $row['budget_gross_profit'] += (float) $amounts['gross_profit'];
    }

    private function applyActualToRow(array &$row, array $amounts): void
    {
        $row['actual_sales'] += (float) $amounts['sales'];
        $row['actual_purchase'] += (float) $amounts['purchase'];
        $row['actual_purchase_material'] += (float) $amounts['purchase_material'];
        $row['actual_purchase_labor'] += (float) $amounts['purchase_labor'];
        $row['actual_gross_profit'] += (float) $amounts['gross_profit'];
    }

    private function applyMaintenanceSnapshots(Collection &$monthlyBySection, array &$rankingBuckets, Carbon $horizonStart, Carbon $horizonEnd, Carbon $currentStart, string $timezone): void
    {
        if (!Schema::hasTable('maintenance_fee_snapshots')) {
            return;
        }

        $snapshots = MaintenanceFeeSnapshot::query()
            ->whereBetween('month', [$horizonStart->toDateString(), $horizonEnd->toDateString()])
            ->with('items')
            ->get(['month', 'total_fee', 'total_gross']);

        foreach ($snapshots as $snapshot) {
            if (!$snapshot->month) {
                continue;
            }

            $monthKey = Carbon::parse($snapshot->month, $timezone)->startOfMonth()->toDateString();
            $sales = (float) ($snapshot->total_fee ?? 0);
            $gross = (float) ($snapshot->total_gross ?? $sales);

            foreach (['overall', 'maintenance'] as $sectionKey) {
                if (!$monthlyBySection[$sectionKey]->has($monthKey)) {
                    continue;
                }

                $row = $monthlyBySection[$sectionKey]->get($monthKey);
                $row['budget_sales'] += $sales;
                $row['actual_sales'] += $sales;
                $row['budget_gross_profit'] += $gross;
                $row['actual_gross_profit'] += $gross;
                $row['budget_collection_inflow'] += $sales;
                $row['actual_collection_inflow'] += $sales;
                $row['budget_count'] += 1;
                $row['actual_count'] += 1;
                $monthlyBySection[$sectionKey]->put($monthKey, $row);
            }

            if ($monthKey === $currentStart->toDateString()) {
                foreach ($snapshot->items as $item) {
                    $this->appendRanking(
                        $rankingBuckets['maintenance'],
                        (string) ($item->customer_name ?? '未設定顧客'),
                        (string) ($item->support_type ?? '保守'),
                        (float) ($item->maintenance_fee ?? 0),
                        (float) ($item->maintenance_fee ?? 0)
                    );
                    $this->appendRanking(
                        $rankingBuckets['overall'],
                        (string) ($item->customer_name ?? '未設定顧客'),
                        '保守',
                        (float) ($item->maintenance_fee ?? 0),
                        (float) ($item->maintenance_fee ?? 0)
                    );
                }
            }
        }
    }

    private function applyPaidBillings(Collection $monthly, Carbon $horizonStart, Carbon $horizonEnd, string $timezone): void
    {
        if (!Schema::hasTable('billings')) {
            return;
        }

        $paidBillings = Billing::query()
            ->whereBetween('due_date', [$horizonStart->toDateString(), $horizonEnd->toDateString()])
            ->get(['id', 'due_date', 'payment_status', 'total_price', 'subtotal_price']);

        foreach ($paidBillings as $billing) {
            if (!$this->isPaidStatus((string) ($billing->payment_status ?? '')) || !$billing->due_date) {
                continue;
            }

            $dueMonthKey = Carbon::parse($billing->due_date, $timezone)->startOfMonth()->toDateString();
            if (!$monthly->has($dueMonthKey)) {
                continue;
            }

            $row = $monthly->get($dueMonthKey);
            $row['actual_collection_inflow'] += $this->resolveBillingAmount($billing);
            $monthly->put($dueMonthKey, $row);
        }
    }

    private function buildSectionPayload(
        array $meta,
        Collection $monthly,
        Carbon $currentStart,
        Carbon $previousStart,
        Carbon $previousYearCurrentStart,
        Carbon $currentYearStart,
        Carbon $currentYearEnd,
        Carbon $previousYearStart,
        Carbon $previousYearEnd,
        float $monthlyCapacity,
        array $rankingBucket,
        array $peopleBucket,
        float $capacityPerPersonDays,
        array $capacityMap
    ): array
    {
        $currentRow = $monthly->get($currentStart->toDateString(), []);
        $previousRow = $monthly->get($previousStart->toDateString(), []);
        $previousYearCurrentRow = $monthly->get($previousYearCurrentStart->toDateString(), []);

        $forecastRows = $monthly
            ->filter(fn (array $row) => $row['month_key'] >= $currentYearStart->toDateString() && $row['month_key'] <= $currentYearEnd->toDateString())
            ->values()
            ->map(function (array $row) {
            return [
                ...$row,
                'sales_variance' => (float) ($row['actual_sales'] - $row['budget_sales']),
                'gross_profit_variance' => (float) ($row['actual_gross_profit'] - $row['budget_gross_profit']),
                'purchase_variance' => (float) ($row['actual_purchase'] - $row['budget_purchase']),
                'budget_net_cash' => (float) ($row['budget_collection_inflow'] - $row['budget_purchase_outflow']),
                'actual_net_cash' => (float) ($row['actual_collection_inflow'] - $row['actual_purchase_outflow']),
            ];
        })->values()->toArray();

        $yoyChartRows = $this->buildYearOverYearChartRows($monthly, $currentYearStart, $currentYearEnd);
        $currentYtdRows = $monthly
            ->filter(fn (array $row) => $row['month_key'] >= $currentYearStart->toDateString() && $row['month_key'] <= $currentStart->toDateString())
            ->values();
        $previousYtdRows = $monthly
            ->filter(function (array $row) use ($previousYearStart, $currentStart) {
                $end = $previousYearStart->copy()->addMonthsNoOverflow($currentStart->month - 1)->endOfMonth()->toDateString();
                return $row['month_key'] >= $previousYearStart->toDateString() && $row['month_key'] <= $end;
            })
            ->values();

        $currentYtdBudget = $this->aggregateRows($currentYtdRows, 'budget');
        $currentYtdActual = $this->aggregateRows($currentYtdRows, 'actual');
        $previousYtdBudget = $this->aggregateRows($previousYtdRows, 'budget');
        $previousYtdActual = $this->aggregateRows($previousYtdRows, 'actual');

        $currentBudgetEffort = (float) ($currentRow['budget_effort'] ?? 0);
        $previousBudgetEffort = (float) ($previousRow['budget_effort'] ?? 0);
        $previousYearBudgetEffort = (float) ($previousYearCurrentRow['budget_effort'] ?? 0);

        return [
            'label' => $meta['label'],
            'description' => $meta['description'],
            'budget' => [
                'current' => $this->mapBudgetRow($currentRow, $currentBudgetEffort, $monthlyCapacity),
                'previous' => $this->mapBudgetRow($previousRow, $previousBudgetEffort, $monthlyCapacity),
                'previous_year_current' => $this->mapBudgetRow($previousYearCurrentRow, $previousYearBudgetEffort, $monthlyCapacity),
            ],
            'actual' => [
                'current' => $this->mapActualRow($currentRow),
                'previous' => $this->mapActualRow($previousRow),
                'previous_year_current' => $this->mapActualRow($previousYearCurrentRow),
            ],
            'effort' => [
                'current' => [
                    'capacity' => $monthlyCapacity,
                    'planned' => $currentBudgetEffort,
                    'planned_remaining' => $monthlyCapacity - $currentBudgetEffort,
                    'planned_fill_rate' => $this->calculateRate($currentBudgetEffort, $monthlyCapacity),
                ],
                'previous' => [
                    'capacity' => $monthlyCapacity,
                    'planned' => $previousBudgetEffort,
                    'planned_remaining' => $monthlyCapacity - $previousBudgetEffort,
                    'planned_fill_rate' => $this->calculateRate($previousBudgetEffort, $monthlyCapacity),
                ],
                'previous_year_current' => [
                    'capacity' => $monthlyCapacity,
                    'planned' => $previousYearBudgetEffort,
                    'planned_remaining' => $monthlyCapacity - $previousYearBudgetEffort,
                    'planned_fill_rate' => $this->calculateRate($previousYearBudgetEffort, $monthlyCapacity),
                ],
                'summary' => [
                    'assigned_total' => 0,
                    'unscheduled_total' => 0,
                ],
            ],
            'cash_flow' => [
                'current' => $this->mapCashRow($currentRow),
                'previous' => $this->mapCashRow($previousRow),
                'previous_year_current' => $this->mapCashRow($previousYearCurrentRow),
            ],
            'forecast' => [
                'months' => $forecastRows,
            ],
            'year_over_year' => [
                'current' => $this->buildYearOverYearPayload(
                    $this->mapBudgetRow($currentRow, $currentBudgetEffort, $monthlyCapacity),
                    $this->mapActualRow($currentRow),
                    $this->mapBudgetRow($previousYearCurrentRow, $previousYearBudgetEffort, $monthlyCapacity),
                    $this->mapActualRow($previousYearCurrentRow),
                    $this->mapCashRow($currentRow),
                    $this->mapCashRow($previousYearCurrentRow)
                ),
                'ytd' => $this->buildYearOverYearPayload(
                    $currentYtdBudget,
                    $currentYtdActual,
                    $previousYtdBudget,
                    $previousYtdActual,
                    [
                        'net_budget' => (float) ($currentYtdBudget['net_cash'] ?? 0),
                        'net_actual' => (float) ($currentYtdActual['net_cash'] ?? 0),
                    ],
                    [
                        'net_budget' => (float) ($previousYtdBudget['net_cash'] ?? 0),
                        'net_actual' => (float) ($previousYtdActual['net_cash'] ?? 0),
                    ]
                ),
                'chart' => $yoyChartRows,
            ],
            'highlights' => [
                'gross_margin_current' => $this->calculateRate((float) ($currentRow['actual_gross_profit'] ?? 0), (float) ($currentRow['actual_sales'] ?? 0)),
                'gross_margin_budget_current' => $this->calculateRate((float) ($currentRow['budget_gross_profit'] ?? 0), (float) ($currentRow['budget_sales'] ?? 0)),
            ],
            'rankings' => [
                'customers' => $this->normalizeRankingRows($rankingBucket['customers'] ?? []),
                'staff' => $this->normalizeRankingRows($rankingBucket['staff'] ?? []),
            ],
            'people' => $this->buildPeoplePayload($peopleBucket, $capacityPerPersonDays, $capacityMap),
            'alerts' => $this->buildSectionAlerts($currentRow, $monthlyCapacity),
        ];
    }

    private function buildYearOverYearChartRows(Collection $monthly, Carbon $currentYearStart, Carbon $currentYearEnd): array
    {
        return $monthly
            ->filter(fn (array $row) => $row['month_key'] >= $currentYearStart->toDateString() && $row['month_key'] <= $currentYearEnd->toDateString())
            ->values()
            ->map(function (array $row) use ($monthly) {
                $month = Carbon::parse($row['month_key'])->subYear()->startOfMonth()->toDateString();
                $lastYearRow = $monthly->get($month, []);

                return [
                    'month_key' => $row['month_key'],
                    'month_label' => $row['month_label'],
                    'current_actual_sales' => (float) ($row['actual_sales'] ?? 0),
                    'last_year_actual_sales' => (float) ($lastYearRow['actual_sales'] ?? 0),
                    'current_actual_gross' => (float) ($row['actual_gross_profit'] ?? 0),
                    'last_year_actual_gross' => (float) ($lastYearRow['actual_gross_profit'] ?? 0),
                    'current_effort' => (float) ($row['budget_effort'] ?? 0),
                    'last_year_effort' => (float) ($lastYearRow['budget_effort'] ?? 0),
                ];
            })
            ->all();
    }

    private function aggregateRows(Collection $rows, string $prefix): array
    {
        return [
            'sales' => (float) $rows->sum($prefix . '_sales'),
            'gross_profit' => (float) $rows->sum($prefix . '_gross_profit'),
            'purchase' => (float) $rows->sum($prefix . '_purchase'),
            'purchase_material' => (float) $rows->sum($prefix . '_purchase_material'),
            'purchase_labor' => (float) $rows->sum($prefix . '_purchase_labor'),
            'count' => (int) $rows->sum($prefix . '_count'),
            'effort' => (float) $rows->sum('budget_effort'),
            'utilization_rate' => 0.0,
            'productivity' => 0.0,
            'net_cash' => (float) (
                $rows->sum($prefix . '_collection_inflow') - $rows->sum($prefix . '_purchase_outflow')
            ),
        ];
    }

    private function buildYearOverYearPayload(
        array $currentBudget,
        array $currentActual,
        array $previousBudget,
        array $previousActual,
        array $currentCash,
        array $previousCash
    ): array {
        return [
            'sales' => $this->compareMetric((float) ($currentActual['sales'] ?? 0), (float) ($previousActual['sales'] ?? 0), (float) ($currentBudget['sales'] ?? 0)),
            'gross_profit' => $this->compareMetric((float) ($currentActual['gross_profit'] ?? 0), (float) ($previousActual['gross_profit'] ?? 0), (float) ($currentBudget['gross_profit'] ?? 0)),
            'purchase' => $this->compareMetric((float) ($currentActual['purchase'] ?? 0), (float) ($previousActual['purchase'] ?? 0), (float) ($currentBudget['purchase'] ?? 0)),
            'effort' => $this->compareMetric((float) ($currentBudget['effort'] ?? 0), (float) ($previousBudget['effort'] ?? 0), (float) ($currentBudget['effort'] ?? 0)),
            'gross_margin' => $this->compareMetric(
                $this->calculateRate((float) ($currentActual['gross_profit'] ?? 0), (float) ($currentActual['sales'] ?? 0)),
                $this->calculateRate((float) ($previousActual['gross_profit'] ?? 0), (float) ($previousActual['sales'] ?? 0)),
                $this->calculateRate((float) ($currentBudget['gross_profit'] ?? 0), (float) ($currentBudget['sales'] ?? 0))
            ),
            'net_cash' => $this->compareMetric((float) ($currentCash['net_actual'] ?? 0), (float) ($previousCash['net_actual'] ?? 0), (float) ($currentCash['net_budget'] ?? 0)),
        ];
    }

    private function compareMetric(float $currentValue, float $previousValue, float $budgetValue = 0.0): array
    {
        return [
            'current' => $currentValue,
            'previous' => $previousValue,
            'delta' => $currentValue - $previousValue,
            'rate' => $previousValue != 0.0 ? (($currentValue - $previousValue) / abs($previousValue)) * 100 : 0.0,
            'budget' => $budgetValue,
            'budget_delta' => $currentValue - $budgetValue,
        ];
    }

    private function appendRanking(array &$bucket, string $customerName, string $staffName, float $sales, float $gross): void
    {
        $customerKey = trim($customerName) !== '' ? trim($customerName) : '未設定顧客';
        $staffKey = trim($staffName) !== '' ? trim($staffName) : '未設定担当';

        if (!isset($bucket['customers'][$customerKey])) {
            $bucket['customers'][$customerKey] = ['name' => $customerKey, 'sales' => 0.0, 'gross_profit' => 0.0];
        }
        if (!isset($bucket['staff'][$staffKey])) {
            $bucket['staff'][$staffKey] = ['name' => $staffKey, 'sales' => 0.0, 'gross_profit' => 0.0];
        }

        $bucket['customers'][$customerKey]['sales'] += $sales;
        $bucket['customers'][$customerKey]['gross_profit'] += $gross;
        $bucket['staff'][$staffKey]['sales'] += $sales;
        $bucket['staff'][$staffKey]['gross_profit'] += $gross;
    }

    private function normalizeRankingRows(array $rows): array
    {
        return collect($rows)
            ->sortByDesc('sales')
            ->take(5)
            ->values()
            ->map(function (array $row, int $index) {
                return [
                    'rank' => $index + 1,
                    'name' => $row['name'],
                    'sales' => (float) ($row['sales'] ?? 0),
                    'gross_profit' => (float) ($row['gross_profit'] ?? 0),
                ];
            })
            ->all();
    }

    private function appendPeopleAssignments(array &$peopleBuckets, Estimate $estimate, array $productLookup, string $currentMonthKey, array $capacityMap, float $defaultCapacityPersonDays, float $monthRatio = 1.0): void
    {
        if ($monthRatio <= 0) {
            return;
        }

        $items = is_array($estimate->items) ? $estimate->items : [];

        foreach ($items as $item) {
            if (!is_array($item) || $this->shouldExcludeEffortItem($item, $productLookup)) {
                continue;
            }

            $qty = (float) (data_get($item, 'qty') ?? data_get($item, 'quantity', 1));
            if ($qty === 0.0) {
                $qty = 1.0;
            }

            $personDays = $this->toPersonDays($qty, (string) (data_get($item, 'unit') ?? '')) * $monthRatio;
            if ($personDays <= 0) {
                continue;
            }

            $sectionKey = $this->resolveSectionKeyForItem($item, $productLookup) ?? 'development';
            $assignees = collect(data_get($item, 'assignees', []))
                ->filter(fn ($assignee) => is_array($assignee))
                ->values();

            if ($assignees->isEmpty()) {
                $this->appendUnassignedPeopleEffort($peopleBuckets['overall'], $personDays);
                if (isset($peopleBuckets[$sectionKey])) {
                    $this->appendUnassignedPeopleEffort($peopleBuckets[$sectionKey], $personDays);
                }

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

                $this->appendPeopleBucketRow($peopleBuckets['overall'], $assignee, $assignedPersonDays, $estimate, $item, $currentMonthKey, $capacityMap, $defaultCapacityPersonDays);
                if (isset($peopleBuckets[$sectionKey])) {
                    $this->appendPeopleBucketRow($peopleBuckets[$sectionKey], $assignee, $assignedPersonDays, $estimate, $item, $currentMonthKey, $capacityMap, $defaultCapacityPersonDays);
                }
            }
        }
    }

    private function appendPeopleBucketRow(array &$bucket, array $assignee, float $assignedPersonDays, Estimate $estimate, array $item, string $currentMonthKey, array $capacityMap, float $defaultCapacityPersonDays): void
    {
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

        if (!isset($bucket['rows'][$key])) {
            $capacityPersonDays = $this->resolveAssigneeCapacityPersonDays($assignee, $capacityMap, $defaultCapacityPersonDays);
            $bucket['rows'][$key] = [
                'user_id' => $assignee['user_id'] ?? null,
                'name' => $displayName,
                'capacity_person_days' => $capacityPersonDays,
                'planned_person_days' => 0.0,
                'estimate_ids' => [],
                'item_count' => 0,
                'share_assignments' => 0,
                'latest_titles' => [],
            ];
        }

        $bucket['rows'][$key]['planned_person_days'] += $assignedPersonDays;
        $bucket['rows'][$key]['item_count'] += 1;
        $bucket['rows'][$key]['share_assignments'] += 1;
        $bucket['rows'][$key]['estimate_ids'][(string) $estimate->id] = true;

        $title = trim((string) ($estimate->title ?? data_get($item, 'name') ?? $currentMonthKey));
        if ($title !== '' && count($bucket['rows'][$key]['latest_titles']) < 3) {
            $bucket['rows'][$key]['latest_titles'][$title] = $title;
        }
    }

    private function appendUnassignedPeopleEffort(array &$bucket, float $personDays): void
    {
        $bucket['unassigned_person_days'] = (float) ($bucket['unassigned_person_days'] ?? 0) + $personDays;
    }

    private function buildPeoplePayload(array $bucket, float $capacityPerPersonDays, array $capacityMap): array
    {
        foreach (($capacityMap['rows'] ?? []) as $capacityRow) {
            $capacityUserId = isset($capacityRow['id']) ? (string) $capacityRow['id'] : null;
            $capacityName = trim((string) ($capacityRow['name'] ?? ''));
            $key = $capacityUserId !== null && $capacityUserId !== '' ? $capacityUserId : $capacityName;

            if ($key === '' || isset($bucket['rows'][$key])) {
                continue;
            }

            $bucket['rows'][$key] = [
                'user_id' => $capacityRow['id'] ?? null,
                'name' => $capacityName !== '' ? $capacityName : '未設定担当',
                'capacity_person_days' => (float) ($capacityRow['resolved_capacity_person_days'] ?? $capacityPerPersonDays),
                'planned_person_days' => 0.0,
                'estimate_ids' => [],
                'item_count' => 0,
                'share_assignments' => 0,
                'latest_titles' => [],
            ];
        }

        $rows = collect($bucket['rows'] ?? [])
            ->map(function (array $row) use ($capacityPerPersonDays, $capacityMap) {
                $plannedPersonDays = (float) ($row['planned_person_days'] ?? 0);
                $resolvedCapacityPersonDays = (float) ($row['capacity_person_days'] ?? 0);
                if ($resolvedCapacityPersonDays <= 0) {
                    $resolvedCapacityPersonDays = $this->resolveAssigneeCapacityPersonDays([
                        'user_id' => $row['user_id'] ?? null,
                        'user_name' => $row['name'] ?? null,
                    ], $capacityMap, $capacityPerPersonDays);
                }
                $availablePersonDays = $resolvedCapacityPersonDays - $plannedPersonDays;

                return [
                    'user_id' => $row['user_id'] ?? null,
                    'name' => (string) ($row['name'] ?? '未設定担当'),
                    'planned_person_days' => round($plannedPersonDays, 1),
                    'capacity_person_days' => round($resolvedCapacityPersonDays, 1),
                    'available_person_days' => round($availablePersonDays, 1),
                    'utilization_rate' => round($this->calculateRate($plannedPersonDays, $resolvedCapacityPersonDays), 1),
                    'estimate_count' => count($row['estimate_ids'] ?? []),
                    'item_count' => (int) ($row['item_count'] ?? 0),
                    'latest_titles' => array_values($row['latest_titles'] ?? []),
                ];
            })
            ->sortByDesc('planned_person_days')
            ->values()
            ->map(function (array $row, int $index) {
                return [
                    'rank' => $index + 1,
                    ...$row,
                ];
            })
            ->all();

        $trackedPeopleCount = count($rows);
        $plannedTotal = collect($rows)->sum('planned_person_days');
        $availableTotal = collect($rows)->sum('available_person_days');
        $overCapacityCount = collect($rows)->filter(fn (array $row) => ($row['utilization_rate'] ?? 0) > 100)->count();
        $highLoadCount = collect($rows)->filter(fn (array $row) => ($row['utilization_rate'] ?? 0) >= 85)->count();
        $availablePeopleCount = collect($rows)->filter(fn (array $row) => ($row['available_person_days'] ?? 0) > 0)->count();
        $topAvailable = collect($rows)
            ->sortByDesc('available_person_days')
            ->take(5)
            ->values()
            ->all();
        $topLoad = collect($rows)
            ->sortByDesc('utilization_rate')
            ->take(5)
            ->values()
            ->all();

        return [
            'tracked_only' => false,
            'capacity_per_person_days' => round($capacityPerPersonDays, 1),
            'summary' => [
                'tracked_people_count' => $trackedPeopleCount,
                'capacity_person_days' => round((float) collect($rows)->sum('capacity_person_days'), 1),
                'planned_person_days' => round((float) $plannedTotal, 1),
                'available_person_days' => round((float) $availableTotal, 1),
                'over_capacity_count' => $overCapacityCount,
                'high_load_count' => $highLoadCount,
                'available_people_count' => $availablePeopleCount,
                'unassigned_person_days' => round((float) ($bucket['unassigned_person_days'] ?? 0), 1),
            ],
            'rows' => $rows,
            'top_available' => $topAvailable,
            'top_load' => $topLoad,
        ];
    }

    private function resolveAssigneeCapacityPersonDays(array $assignee, array $capacityMap, float $defaultCapacityPersonDays): float
    {
        $userId = isset($assignee['user_id']) && $assignee['user_id'] !== ''
            ? (string) $assignee['user_id']
            : null;
        $userName = trim((string) ($assignee['user_name'] ?? ''));

        if ($userId !== null && isset($capacityMap['by_id'][$userId])) {
            return (float) $capacityMap['by_id'][$userId];
        }

        if ($userName !== '' && isset($capacityMap['by_name'][$userName])) {
            return (float) $capacityMap['by_name'][$userName];
        }

        return $defaultCapacityPersonDays;
    }

    private function buildSectionAlerts(array $currentRow, float $monthlyCapacity): array
    {
        $alerts = [];

        $budgetSales = (float) ($currentRow['budget_sales'] ?? 0);
        $actualSales = (float) ($currentRow['actual_sales'] ?? 0);
        $budgetGross = (float) ($currentRow['budget_gross_profit'] ?? 0);
        $actualGross = (float) ($currentRow['actual_gross_profit'] ?? 0);
        $budgetEffort = (float) ($currentRow['budget_effort'] ?? 0);
        $budgetNetCash = (float) (($currentRow['budget_collection_inflow'] ?? 0) - ($currentRow['budget_purchase_outflow'] ?? 0));

        if ($budgetSales > 0 && $actualSales < $budgetSales) {
            $alerts[] = [
                'title' => '売上予算未達',
                'detail' => '実績売上が予算を下回っています。納期後ろ倒し案件や未受注案件の確認が必要です。',
                'tone' => 'negative',
            ];
        }

        if ($budgetGross > 0 && $actualGross < $budgetGross) {
            $alerts[] = [
                'title' => '粗利悪化',
                'detail' => '粗利実績が予算を下回っています。低粗利案件や原価の見落としを確認してください。',
                'tone' => 'negative',
            ];
        }

        if ($monthlyCapacity > 0) {
            $utilization = ($budgetEffort / $monthlyCapacity) * 100;
            if ($utilization >= 100) {
                $alerts[] = [
                    'title' => '工数過負荷',
                    'detail' => '計画工数が月間キャパを超えています。配員見直しまたは納期調整が必要です。',
                    'tone' => 'negative',
                ];
            } elseif ($utilization <= 70 && $budgetEffort > 0) {
                $alerts[] = [
                    'title' => '工数余力あり',
                    'detail' => '工数にはまだ余力があります。前倒し着手や追加提案の余地があります。',
                    'tone' => 'positive',
                ];
            }
        }

        if ($budgetNetCash < 0) {
            $alerts[] = [
                'title' => 'ネットCFマイナス',
                'detail' => '当月のネットキャッシュフロー予定がマイナスです。支払先行案件と回収遅延を確認してください。',
                'tone' => 'negative',
            ];
        }

        return $alerts;
    }

    private function mapBudgetRow(array $row, float $effort, float $monthlyCapacity): array
    {
        return [
            'sales' => (float) ($row['budget_sales'] ?? 0),
            'gross_profit' => (float) ($row['budget_gross_profit'] ?? 0),
            'purchase' => (float) ($row['budget_purchase'] ?? 0),
            'purchase_material' => (float) ($row['budget_purchase_material'] ?? 0),
            'purchase_labor' => (float) ($row['budget_purchase_labor'] ?? 0),
            'effort' => $effort,
            'utilization_rate' => $this->calculateRate($effort, $monthlyCapacity),
            'productivity' => $this->calculateProductivity((float) ($row['budget_gross_profit'] ?? 0), $effort),
            'count' => (int) ($row['budget_count'] ?? 0),
        ];
    }

    private function mapActualRow(array $row): array
    {
        return [
            'sales' => (float) ($row['actual_sales'] ?? 0),
            'gross_profit' => (float) ($row['actual_gross_profit'] ?? 0),
            'purchase' => (float) ($row['actual_purchase'] ?? 0),
            'purchase_material' => (float) ($row['actual_purchase_material'] ?? 0),
            'purchase_labor' => (float) ($row['actual_purchase_labor'] ?? 0),
            'count' => (int) ($row['actual_count'] ?? 0),
        ];
    }

    private function mapCashRow(array $row): array
    {
        return [
            'purchase_outflow_budget' => (float) ($row['budget_purchase_outflow'] ?? 0),
            'purchase_outflow_actual' => (float) ($row['actual_purchase_outflow'] ?? 0),
            'collection_inflow_budget' => (float) ($row['budget_collection_inflow'] ?? 0),
            'collection_inflow_actual' => (float) ($row['actual_collection_inflow'] ?? 0),
            'net_budget' => (float) (($row['budget_collection_inflow'] ?? 0) - ($row['budget_purchase_outflow'] ?? 0)),
            'net_actual' => (float) (($row['actual_collection_inflow'] ?? 0) - ($row['actual_purchase_outflow'] ?? 0)),
        ];
    }

    private function buildAnalysis(array $overall, array $development, array $maintenance): array
    {
        $items = [];

        $salesVariance = (float) (($overall['actual']['current']['sales'] ?? 0) - ($overall['budget']['current']['sales'] ?? 0));
        $grossVariance = (float) (($overall['actual']['current']['gross_profit'] ?? 0) - ($overall['budget']['current']['gross_profit'] ?? 0));
        $yoySalesRate = (float) ($overall['year_over_year']['current']['sales']['rate'] ?? 0);
        $yoyGrossRate = (float) ($overall['year_over_year']['current']['gross_profit']['rate'] ?? 0);
        $utilization = (float) ($development['effort']['current']['planned_fill_rate'] ?? 0);
        $maintenanceSales = (float) ($maintenance['actual']['current']['sales'] ?? 0);

        $items[] = [
            'title' => '売上差異',
            'body' => $salesVariance >= 0
                ? '今月の売上実績は予算を上回っています。前倒し案件と保守売上の寄与を確認してください。'
                : '今月の売上実績は予算未達です。未受注案件と納期後ろ倒し案件の洗い出しが必要です。',
            'tone' => $salesVariance >= 0 ? 'positive' : 'negative',
        ];

        $items[] = [
            'title' => '粗利状況',
            'body' => $grossVariance >= 0
                ? '粗利は計画以上です。販売系比率と開発案件の生産性が維持できているか確認してください。'
                : '粗利が計画を下回っています。低粗利案件、外注比率、原価未反映明細を重点確認してください。',
            'tone' => $grossVariance >= 0 ? 'positive' : 'negative',
        ];

        $items[] = [
            'title' => '前年比',
            'body' => $yoySalesRate >= 0
                ? ($yoyGrossRate >= 0
                    ? '前年同月比で売上・粗利ともに伸長しています。伸びた要因が開発案件か保守継続かを切り分けて再現性を確認してください。'
                    : '売上は前年超えですが粗利が弱含みです。値引き案件や原価上振れを点検してください。')
                : '前年同月比で売上が弱いです。失注、納期後ろ倒し、前倒し計上の反動を分けて確認してください。',
            'tone' => $yoySalesRate >= 0 && $yoyGrossRate >= 0 ? 'positive' : ($yoySalesRate >= 0 ? 'neutral' : 'negative'),
        ];

        $items[] = [
            'title' => '開発稼働',
            'body' => $utilization >= 100
                ? '開発工数は過負荷です。担当者別配員見直しと納期調整が必要です。'
                : ($utilization >= 85
                    ? '開発工数は高稼働です。新規受注時は余力確認を前提にしてください。'
                    : '開発工数にはまだ余力があります。前倒し着手や提案活動の余地があります。'),
            'tone' => $utilization >= 100 ? 'negative' : 'neutral',
        ];

        if ($maintenanceSales > 0) {
            $items[] = [
                'title' => '保守売上',
                'body' => '保守売上は継続収益として安定しています。保守顧客別の単価と工数消化の見える化を次段階で追加してください。',
                'tone' => 'neutral',
            ];
        }

        return $items;
    }

    private function buildSectionAnalysis(string $sectionKey, array $section): array
    {
        $items = [];
        $currentBudget = $section['budget']['current'] ?? [];
        $currentActual = $section['actual']['current'] ?? [];
        $currentEffort = $section['effort']['current'] ?? [];
        $currentCash = $section['cash_flow']['current'] ?? [];
        $yoyCurrent = $section['year_over_year']['current'] ?? [];

        $salesVariance = (float) (($currentActual['sales'] ?? 0) - ($currentBudget['sales'] ?? 0));
        $grossVariance = (float) (($currentActual['gross_profit'] ?? 0) - ($currentBudget['gross_profit'] ?? 0));
        $netCashVariance = (float) (($currentCash['net_actual'] ?? 0) - ($currentCash['net_budget'] ?? 0));
        $yoySalesRate = (float) ($yoyCurrent['sales']['rate'] ?? 0);
        $yoyGrossRate = (float) ($yoyCurrent['gross_profit']['rate'] ?? 0);
        $utilization = (float) ($currentEffort['planned_fill_rate'] ?? 0);

        if (($currentBudget['sales'] ?? 0) > 0 || ($currentActual['sales'] ?? 0) > 0) {
            $items[] = [
                'title' => '売上差異',
                'body' => $salesVariance >= 0
                    ? '当月売上は計画を上回っています。前倒し計上や継続案件の寄与を確認してください。'
                    : '当月売上は計画未達です。未受注案件、納期後ろ倒し、請求タイミングを確認してください。',
                'tone' => $salesVariance >= 0 ? 'positive' : 'negative',
            ];
        }

        if (($currentBudget['gross_profit'] ?? 0) > 0 || ($currentActual['gross_profit'] ?? 0) > 0) {
            $items[] = [
                'title' => $sectionKey === 'sales' ? '販売粗利' : '粗利状況',
                'body' => $grossVariance >= 0
                    ? '粗利は計画水準を維持しています。高粗利案件の再現性を確認してください。'
                    : '粗利が計画を下回っています。値引き、外注比率、原価未反映の有無を確認してください。',
                'tone' => $grossVariance >= 0 ? 'positive' : 'negative',
            ];
        }

        if ($sectionKey === 'development') {
            $items[] = [
                'title' => '開発稼働',
                'body' => $utilization >= 100
                    ? '開発工数は過負荷です。担当者別配員見直しと納期調整が必要です。'
                    : ($utilization >= 85
                        ? '開発工数は高稼働です。追加受注時は担当者余力の確認を優先してください。'
                        : '開発工数にはまだ余力があります。前倒し着手や追加提案の余地があります。'),
                'tone' => $utilization >= 100 ? 'negative' : ($utilization >= 85 ? 'neutral' : 'positive'),
            ];
        }

        if ($sectionKey === 'sales') {
            $items[] = [
                'title' => '販売回収',
                'body' => $netCashVariance >= 0
                    ? '販売案件のネットキャッシュは計画以上です。納品月と請求月のズレがないかだけ確認してください。'
                    : '販売案件のネットキャッシュが計画未達です。納品済み案件の請求・回収予定を優先確認してください。',
                'tone' => $netCashVariance >= 0 ? 'positive' : 'negative',
            ];
        }

        if ($sectionKey === 'maintenance') {
            $items[] = [
                'title' => '保守継続',
                'body' => $yoySalesRate >= 0
                    ? '保守売上は前年水準以上です。単価改定や解約抑止の効果を確認してください。'
                    : '保守売上は前年を下回っています。解約、単価低下、未請求顧客の有無を確認してください。',
                'tone' => $yoySalesRate >= 0 ? 'positive' : 'negative',
            ];
        }

        if (count($items) === 0) {
            $items[] = [
                'title' => '概況',
                'body' => 'この区分は大きな変動が少ない状態です。前年差と案件一覧を見て差分要因を確認してください。',
                'tone' => 'neutral',
            ];
        } elseif ($sectionKey !== 'maintenance') {
            $items[] = [
                'title' => '前年比',
                'body' => $yoySalesRate >= 0
                    ? ($yoyGrossRate >= 0
                        ? '前年同月比では売上・粗利ともに改善傾向です。増加要因が継続案件か単発案件かを切り分けてください。'
                        : '売上は前年超えですが粗利が弱含みです。値引きと原価増を重点確認してください。')
                    : '前年同月比で弱含みです。失注や納期ズレの影響を洗い出してください。',
                'tone' => $yoySalesRate >= 0 && $yoyGrossRate >= 0 ? 'positive' : ($yoySalesRate >= 0 ? 'neutral' : 'negative'),
            ];
        }

        return $items;
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

    private function resolvePurchaseDate(Estimate $estimate, string $timezone): ?Carbon
    {
        $purchaseAt = $estimate->issue_date
            ?? $estimate->due_date
            ?? $estimate->delivery_date;

        if (!$purchaseAt) {
            return null;
        }

        return Carbon::parse($purchaseAt, $timezone);
    }

    private function resolveCollectionDate(Estimate $estimate, string $timezone): ?Carbon
    {
        if ((bool) $estimate->is_order_confirmed === true) {
            $confirmedBaseAt = $estimate->delivery_date
                ?? $estimate->due_date
                ?? $estimate->issue_date;

            if (!$confirmedBaseAt) {
                return null;
            }

            return Carbon::parse($confirmedBaseAt, $timezone)->addMonthNoOverflow();
        }

        $collectionAt = $estimate->due_date;
        if (!$collectionAt) {
            $recognizedAt = $this->resolveRecognitionDate($estimate, $timezone);
            if (!$recognizedAt) {
                return null;
            }

            return $recognizedAt->copy()->addMonthNoOverflow();
        }

        return Carbon::parse($collectionAt, $timezone);
    }

    private function shouldExcludeEffortItem($item, array $productLookup): bool
    {
        $product = $this->resolveProductForItem($item, $productLookup);
        if ($product && (($product['business_division'] ?? null) === 'first_business')) {
            return true;
        }

        $unit = mb_strtolower((string) (data_get($item, 'unit') ?? ''));

        return !($unit === '' || str_contains($unit, '人日') || str_contains($unit, '人月') || str_contains($unit, '人時') || str_contains($unit, '時間') || $unit === 'h' || $unit === 'hr');
    }

    private function resolveProductForItem($item, array $productLookup): ?array
    {
        $productId = data_get($item, 'product_id') ?? data_get($item, 'productId') ?? data_get($item, 'product.id');
        if ($productId !== null && isset($productLookup['by_id'][(int) $productId])) {
            return $productLookup['by_id'][(int) $productId];
        }

        $sku = mb_strtolower(trim((string) (data_get($item, 'code') ?? data_get($item, 'product_code') ?? data_get($item, 'sku') ?? '')));
        if ($sku !== '' && isset($productLookup['by_sku'][$sku])) {
            return $productLookup['by_sku'][$sku];
        }

        $name = mb_strtolower(trim((string) (data_get($item, 'name') ?? data_get($item, 'product_name') ?? '')));
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

    private function calculateProductivity(float $grossProfit, float $effortPersonDays): float
    {
        if ($effortPersonDays <= 0.0) {
            return 0.0;
        }

        return $grossProfit / $effortPersonDays;
    }

    private function isPaidStatus(string $status): bool
    {
        $normalized = mb_strtolower(trim($status));
        if ($normalized === '') {
            return false;
        }

        return str_contains($normalized, 'paid')
            || str_contains($normalized, '入金済')
            || str_contains($normalized, '入金完了')
            || str_contains($normalized, '支払済');
    }

    private function resolveBillingAmount(Billing $billing): float
    {
        $total = (float) ($billing->total_price ?? 0);
        if ($total > 0) {
            return $total;
        }

        return (float) ($billing->subtotal_price ?? 0);
    }
}
