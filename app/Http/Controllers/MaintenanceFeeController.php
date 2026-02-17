<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use App\Models\MaintenanceFeeSnapshot;
use Carbon\Carbon;
use App\Models\MaintenanceFeeSnapshotItem;

class MaintenanceFeeController extends Controller
{
    public function resyncCurrentMonth(Request $request)
    {
        $month = Carbon::now()->startOfMonth();

        $snapshot = MaintenanceFeeSnapshot::whereDate('month', $month)->first();
        if (!$snapshot) {
            $snapshot = MaintenanceFeeSnapshot::create([
                'month' => $month,
                'total_fee' => 0,
                'total_gross' => 0,
                'source' => 'api',
            ]);
        }

        $this->populateSnapshotItemsFromApi($snapshot);
        $itemCount = $snapshot->fresh()->items()->count();

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
            ->map(function ($m) {
                return $m instanceof Carbon ? $m->toDateString() : (string) $m;
            })
            ->unique()
            ->values();
        if ($selectedMonth === '' && $snapshotMonths->isNotEmpty()) {
            $selectedMonth = $snapshotMonths->last();
        }

        $snapshot = $this->getOrCreateSnapshotWithItems($selectedMonth);
        $itemsCollection = $snapshot ? $snapshot->items : collect();

        $filtered = $itemsCollection
            ->filter(function ($c) {
                return (float) ($c['maintenance_fee'] ?? 0) > 0;
            })
            ->filter(function ($c) use ($search, $supportType) {
                if ($search !== '') {
                    $name = (string) ($c['customer_name'] ?? '');
                    if (stripos($name, $search) === false) {
                        return false;
                    }
                }
                if ($supportType !== '') {
                    $supportStr = (string) ($c['support_type'] ?? '');
                    return $supportStr === $supportType;
                }
                return true;
            })
            ->values();

        $totalFee = $filtered->sum(fn($c) => (float) ($c['maintenance_fee'] ?? 0));
        $activeCount = $filtered->count();
        $averageFee = $activeCount > 0 ? $totalFee / $activeCount : 0;

        // support_type の候補リストを作成
        $supportTypes = $filtered->flatMap(function ($c) {
                $raw = $c['support_type'] ?? '';
                $merged = is_array($raw) ? $raw : preg_split('/[\\s,、\\/]+/u', (string) $raw);
                return collect($merged)->filter(fn($v) => $v !== null && $v !== '')->values();
            })
            ->unique()
            ->values()
            ->all();

        $chart = $snapshots->sortByDesc('month')->take(6)->sortBy('month')->values()->map(function ($s) {
            return [
                'month' => $s->month,
                'label' => Carbon::parse($s->month)->format('Y/m'),
                'total' => (float) $s->total_fee,
            ];
        });

        $availableYears = $snapshotMonths->map(fn ($m) => substr($m, 0, 4))->unique()->values();
        $monthsByYear = $snapshotMonths
            ->groupBy(fn ($m) => substr($m, 0, 4))
            ->map(function ($list) {
                return $list->map(fn ($m) => substr($m, 5, 2))->unique()->values();
            });

        return Inertia::render('MaintenanceFees/Index', [
            'items' => $filtered->map(function ($c) {
                $supportStr = (string) ($c['support_type'] ?? '');
                $supportTypes = collect(preg_split('/[\\s,、\\/]+/u', $supportStr))
                    ->filter(fn ($s) => $s !== '')
                    ->values()
                    ->all();

                return [
                    'id' => $c['id'] ?? null,
                    'customer_name' => $c['customer_name'] ?? '',
                    'support_type' => $supportStr,
                    'support_types' => $supportTypes,
                    'maintenance_fee' => (float) ($c['maintenance_fee'] ?? 0),
                    'status' => (string) ($c['status'] ?? ''),
                ];
            }),
            'summary' => [
                'total_fee' => $snapshot?->total_fee ?? $totalFee,
                'active_count' => $activeCount,
                'average_fee' => $activeCount > 0 ? ($totalFee / $activeCount) : 0,
                'snapshot_month' => $snapshot?->month?->toDateString(),
            ],
            'filters' => [
                'search' => $search,
                'support_type' => $supportType,
                'support_type_options' => $supportTypes,
                'selected_month' => $selectedMonth,
                'available_years' => $availableYears,
                'months_by_year' => $monthsByYear,
            ],
            'snapshots' => $snapshots->map(fn ($s) => [
                'month' => optional($s->month)->toDateString(),
                'total_fee' => (float) $s->total_fee,
            ]),
            'chart' => $chart,
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

        $snapshot = $this->getOrCreateSnapshotWithItems($data['month']);
        if (!$snapshot) {
            return back()->withErrors(['month' => 'スナップショットが作成できませんでした。']);
        }

        MaintenanceFeeSnapshotItem::create([
            'maintenance_fee_snapshot_id' => $snapshot->id,
            'customer_name' => $data['customer_name'],
            'maintenance_fee' => $data['maintenance_fee'],
            'status' => $data['status'] ?? null,
            'support_type' => $data['support_type'] ?? null,
        ]);

        $this->recalculateSnapshotTotal($snapshot);

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

        $item->update($data);
        $this->recalculateSnapshotTotal($item->snapshot);

        return redirect()->route('maintenance-fees.index', ['month' => $item->snapshot->month->format('Y-m')]);
    }

    public function deleteItem(MaintenanceFeeSnapshotItem $item)
    {
        $snapshot = $item->snapshot;
        $item->delete();
        if ($snapshot) {
            $this->recalculateSnapshotTotal($snapshot);
        }
        return redirect()->route('maintenance-fees.index', ['month' => optional($snapshot?->month)->format('Y-m')]);
    }

    private function recalculateSnapshotTotal(MaintenanceFeeSnapshot $snapshot): void
    {
        $sum = $snapshot->items()->sum('maintenance_fee');
        $snapshot->total_fee = $sum;
        $snapshot->total_gross = $sum;
        $snapshot->save();
    }

    private function getOrCreateSnapshotWithItems(?string $monthInput): ?MaintenanceFeeSnapshot
    {
        if ($monthInput === null || $monthInput === '') {
            return null;
        }
        try {
            // 許容フォーマット: Y-m または Y-m-d
            $month = Carbon::parse($monthInput)->startOfMonth();
        } catch (\Throwable $e) {
            return null;
        }
        $snapshot = MaintenanceFeeSnapshot::with('items')->whereDate('month', $month)->first();
        if ($snapshot) {
            if ($snapshot->items->isEmpty()) {
                $this->populateSnapshotItemsFromApi($snapshot);
                $snapshot->load('items');
            }
            return $snapshot;
        }

        $customers = $this->fetchCustomers();
        $snapshot = MaintenanceFeeSnapshot::create([
            'month' => $month,
            'total_fee' => 0,
            'total_gross' => 0,
            'source' => 'api',
        ]);

        $rows = $this->buildRowsFromCustomers($snapshot->id, $customers);
        if (!empty($rows)) {
            MaintenanceFeeSnapshotItem::insert($rows);
        }
        $this->recalculateSnapshotTotal($snapshot->fresh('items'));
        return $snapshot->fresh('items');
    }

    private function fetchCustomers(): array
    {
        $base = rtrim((string) env('EXTERNAL_API_BASE', 'https://api.xerographix.co.jp/public/api'), '/');
        $token = (string) env('EXTERNAL_API_TOKEN', '');

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => $token ? 'Bearer ' . $token : null,
            ])->get($base . '/customers');

            if (!$response->successful()) {
                \Log::warning('Failed to fetch maintenance customers', [
                    'status' => $response->status(),
                    'url' => $base . '/customers',
                    'body' => $response->body(),
                ]);
                return [];
            }

            $json = $response->json();
            return is_array($json) ? $json : [];
        } catch (\Throwable $e) {
            \Log::error('Error fetching maintenance customers', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function populateSnapshotItemsFromApi(MaintenanceFeeSnapshot $snapshot): void
    {
        $customers = $this->fetchCustomers();
        if (empty($customers)) {
            return;
        }
        $rows = $this->buildRowsFromCustomers($snapshot->id, $customers);
        if (!empty($rows)) {
            MaintenanceFeeSnapshotItem::where('maintenance_fee_snapshot_id', $snapshot->id)->delete();
            MaintenanceFeeSnapshotItem::insert($rows);
            $this->recalculateSnapshotTotal($snapshot->fresh('items'));
        }
    }

    private function buildRowsFromCustomers(int $snapshotId, array $customers): array
    {
        $rows = [];
        $now = now();
        foreach ($customers as $c) {
            $fee = (float) ($c['maintenance_fee'] ?? 0);
            $status = (string) ($c['status'] ?? $c['customer_status'] ?? $c['status_name'] ?? '');
            if ($status !== '' && (
                mb_stripos($status, '休止') !== false ||
                mb_strtolower($status) === 'inactive'
            )) {
                continue;
            }
            if ($fee <= 0) {
                continue;
            }
            $rawSupport = $c['support_type'] ?? '';
            $supportStr = is_array($rawSupport) ? implode(' ', $rawSupport) : (string) $rawSupport;
            $rows[] = [
                'maintenance_fee_snapshot_id' => $snapshotId,
                'customer_name' => $c['customer_name'] ?? '',
                'maintenance_fee' => $fee,
                'status' => $status,
                'support_type' => $supportStr,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        return $rows;
    }
}
