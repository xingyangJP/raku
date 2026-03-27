<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceFeeSnapshot;
use App\Models\MaintenanceFeeSnapshotItem;
use App\Services\MaintenanceFeeSyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceFeeController extends Controller
{
    public function __construct(
        private readonly MaintenanceFeeSyncService $syncService
    ) {
    }

    public function resyncCurrentMonth(Request $request)
    {
        $month = Carbon::now()->startOfMonth();
        $result = $this->syncService->resyncMonth($month);
        $snapshot = $result['snapshot'];

        if ($result['error']) {
            return redirect()->route('maintenance-fees.index', [
                'month' => $month->format('Y-m'),
            ])->with('error', $result['error']);
        }

        $itemCount = $snapshot?->items()->count() ?? 0;

        return redirect()->route('maintenance-fees.index', [
            'month' => $month->format('Y-m'),
        ])->with('success', "当月保守売上を再同期しました（{$itemCount}件）。");
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $supportType = trim((string) $request->query('support_type', ''));
        $selectedMonth = trim((string) $request->query('month', ''));

        $snapshots = MaintenanceFeeSnapshot::query()
            ->orderBy('month')
            ->get();

        $snapshotMonths = $snapshots->pluck('month')
            ->filter()
            ->map(fn ($month) => $month instanceof Carbon ? $month->toDateString() : (string) $month)
            ->unique()
            ->values();

        if ($selectedMonth === '' && $snapshotMonths->isNotEmpty()) {
            $selectedMonth = $snapshotMonths->last();
        } elseif ($selectedMonth === '') {
            $selectedMonth = Carbon::now()->format('Y-m');
        }

        $result = $this->syncService->getSnapshotForMonth($selectedMonth);
        /** @var MaintenanceFeeSnapshot|null $snapshot */
        $snapshot = $result['snapshot'];
        $allItems = $snapshot?->items ?? collect();

        $supportTypeOptions = $this->syncService->extractSupportTypes($allItems);

        $filteredItems = $allItems
            ->filter(fn ($item) => (float) ($item['maintenance_fee'] ?? 0) > 0)
            ->filter(function ($item) use ($search, $supportType) {
                $name = (string) ($item['customer_name'] ?? '');
                $itemSupportTypes = $this->syncService->splitSupportTypes((string) ($item['support_type'] ?? ''));

                if ($search !== '' && stripos($name, $search) === false) {
                    return false;
                }

                if ($supportType !== '' && !in_array($supportType, $itemSupportTypes, true)) {
                    return false;
                }

                return true;
            })
            ->values();

        $overallTotal = $allItems->sum(fn ($item) => (float) ($item['maintenance_fee'] ?? 0));
        $overallCount = $allItems->count();
        $filteredTotal = $filteredItems->sum(fn ($item) => (float) ($item['maintenance_fee'] ?? 0));
        $filteredCount = $filteredItems->count();

        $chartReferenceMonth = $snapshot?->month
            ? Carbon::parse($snapshot->month)->startOfMonth()
            : ($snapshotMonths->isNotEmpty() ? Carbon::parse($snapshotMonths->last())->startOfMonth() : Carbon::now()->startOfMonth());
        $chartStartMonth = $chartReferenceMonth->copy()->subMonths(5);

        $chart = $snapshots
            ->filter(function ($item) use ($chartStartMonth, $chartReferenceMonth) {
                $month = $item->month instanceof Carbon ? $item->month->copy()->startOfMonth() : Carbon::parse($item->month)->startOfMonth();

                return $month->betweenIncluded($chartStartMonth, $chartReferenceMonth);
            })
            ->sortBy('month')
            ->values()
            ->map(fn ($item) => [
                'month' => $item->month,
                'label' => Carbon::parse($item->month)->format('Y/m'),
                'total' => (float) $item->total_fee,
            ]);

        $availableYears = $snapshotMonths->map(fn ($month) => substr($month, 0, 4))->unique()->values();
        $monthsByYear = $snapshotMonths
            ->groupBy(fn ($month) => substr($month, 0, 4))
            ->map(fn ($months) => $months->map(fn ($month) => substr($month, 5, 2))->unique()->values());

        return Inertia::render('MaintenanceFees/Index', [
            'items' => $filteredItems->map(function ($item) {
                $supportType = (string) ($item['support_type'] ?? '');

                return [
                    'id' => $item['id'] ?? null,
                    'customer_name' => $item['customer_name'] ?? '',
                    'support_type' => $supportType,
                    'support_types' => $this->syncService->splitSupportTypes($supportType),
                    'maintenance_fee' => (float) ($item['maintenance_fee'] ?? 0),
                    'status' => (string) ($item['status'] ?? ''),
                    'entry_source' => (string) ($item['entry_source'] ?? MaintenanceFeeSyncService::ITEM_SOURCE_API),
                ];
            })->values(),
            'summary' => [
                'snapshot_month' => $snapshot?->month?->toDateString(),
                'displayed_total_fee' => $filteredTotal,
                'displayed_active_count' => $filteredCount,
                'displayed_average_fee' => $filteredCount > 0 ? ($filteredTotal / $filteredCount) : 0,
                'overall_total_fee' => $overallTotal,
                'overall_active_count' => $overallCount,
                'overall_average_fee' => $overallCount > 0 ? ($overallTotal / $overallCount) : 0,
                'meta' => [
                    'source' => $snapshot?->source,
                    'source_label' => $this->syncService->sourceLabel($snapshot?->source),
                    'last_synced_at' => $this->syncService->displaySyncedAt($snapshot),
                    'manual_edit_count' => $this->syncService->manualEditCount($snapshot),
                    'has_filters' => ($search !== '' || $supportType !== ''),
                    'applied_filters' => [
                        'search' => $search,
                        'support_type' => $supportType,
                    ],
                ],
            ],
            'filters' => [
                'search' => $search,
                'support_type' => $supportType,
                'support_type_options' => $supportTypeOptions,
                'selected_month' => $selectedMonth,
                'available_years' => $availableYears,
                'months_by_year' => $monthsByYear,
            ],
            'snapshots' => $snapshots->map(fn ($item) => [
                'month' => optional($item->month)->toDateString(),
                'total_fee' => (float) $item->total_fee,
            ]),
            'chart' => $chart,
            'api_status' => [
                'kind' => $result['error'] ? 'error' : 'ok',
                'message' => $result['error'],
            ],
        ]);
    }

    public function storeItem(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'customer_name' => 'required|string|max:255',
            'maintenance_fee' => 'required|numeric|min:0',
            'status' => 'nullable|string|max:100',
            'support_type' => 'nullable|string|max:255',
        ]);

        $result = $this->syncService->getSnapshotForMonth($data['month']);
        /** @var MaintenanceFeeSnapshot|null $snapshot */
        $snapshot = $result['snapshot'];
        if (!$snapshot) {
            return back()->withErrors(['month' => $result['error'] ?? 'スナップショットが作成できませんでした。']);
        }

        MaintenanceFeeSnapshotItem::create([
            'maintenance_fee_snapshot_id' => $snapshot->id,
            'customer_name' => $data['customer_name'],
            'maintenance_fee' => $data['maintenance_fee'],
            'status' => $data['status'] ?? null,
            'support_type' => $data['support_type'] ?? null,
            'entry_source' => MaintenanceFeeSyncService::ITEM_SOURCE_MANUAL,
        ]);

        $this->syncService->recalculateSnapshot($snapshot->fresh('items'));

        return redirect()->route('maintenance-fees.index', ['month' => $snapshot->month->format('Y-m')]);
    }

    public function updateItem(Request $request, MaintenanceFeeSnapshotItem $item)
    {
        $data = $request->validate([
            'customer_name' => 'required|string|max:255',
            'maintenance_fee' => 'required|numeric|min:0',
            'status' => 'nullable|string|max:100',
            'support_type' => 'nullable|string|max:255',
        ]);

        $item->update(array_merge($data, [
            'entry_source' => MaintenanceFeeSyncService::ITEM_SOURCE_MANUAL,
        ]));

        $this->syncService->recalculateSnapshot($item->snapshot->fresh('items'));

        return redirect()->route('maintenance-fees.index', ['month' => $item->snapshot->month->format('Y-m')]);
    }

    public function deleteItem(MaintenanceFeeSnapshotItem $item)
    {
        $snapshot = $item->snapshot;
        $item->delete();

        if ($snapshot) {
            $this->syncService->recalculateSnapshot($snapshot->fresh('items'));
        }

        return redirect()->route('maintenance-fees.index', ['month' => optional($snapshot?->month)->format('Y-m')]);
    }
}
