<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use App\Models\MaintenanceFeeSnapshot;
use Carbon\Carbon;

class MaintenanceFeeController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $supportType = trim((string) $request->query('support_type', ''));
        $selectedMonth = trim((string) $request->query('month', ''));

        $snapshots = MaintenanceFeeSnapshot::query()
            ->orderBy('month')
            ->get(['month', 'total_fee', 'total_gross']);

        $snapshotMonths = $snapshots->pluck('month')->filter()->unique()->values();
        if ($selectedMonth === '' && $snapshotMonths->isNotEmpty()) {
            $selectedMonth = $snapshotMonths->last();
        }

        $snapshotSummary = null;
        if ($selectedMonth !== '') {
            $snapshotSummary = $snapshots->firstWhere('month', $selectedMonth);
        }

        $customers = $this->fetchCustomers();

        // 0円を除外
        $filtered = collect($customers)
            ->filter(function ($c) {
                $fee = $c['maintenance_fee'] ?? 0;
                $status = (string) ($c['status'] ?? $c['customer_status'] ?? $c['status_name'] ?? '');
                if ($status !== '' && (
                    mb_stripos($status, '休止') !== false ||
                    mb_strtolower($status) === 'inactive'
                )) {
                    return false;
                }
                return $fee > 0;
            })
            ->filter(function ($c) use ($search, $supportType) {
                if ($search !== '') {
                    $name = (string) ($c['customer_name'] ?? '');
                    if (stripos($name, $search) === false) {
                        return false;
                    }
                }
                if ($supportType !== '') {
                    $rawSupport = $c['support_type'] ?? '';
                    $supportStr = is_array($rawSupport) ? implode(' ', $rawSupport) : (string) $rawSupport;
                    return $supportStr === $supportType || (is_array($rawSupport) && in_array($supportType, $rawSupport, true));
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
                $status = (string) ($c['status'] ?? $c['customer_status'] ?? $c['status_name'] ?? '');
                $rawSupport = $c['support_type'] ?? '';
                $supportTypes = collect(is_array($rawSupport) ? $rawSupport : preg_split('/[\\s,、\\/]+/u', (string) $rawSupport))
                    ->filter(fn ($s) => $s !== '')
                    ->values()
                    ->all();

                return [
                    'customer_name' => $c['customer_name'] ?? '',
                    'support_type' => is_array($rawSupport) ? implode(' ', $rawSupport) : $rawSupport,
                    'support_types' => $supportTypes,
                    'maintenance_fee' => (float) ($c['maintenance_fee'] ?? 0),
                    'status' => $status,
                ];
            }),
            'summary' => [
                'total_fee' => $snapshotSummary?->total_fee ?? $totalFee,
                'active_count' => $activeCount,
                'average_fee' => $activeCount > 0 ? ($totalFee / $activeCount) : 0,
                'snapshot_month' => $snapshotSummary?->month,
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
                'month' => $s->month,
                'total_fee' => (float) $s->total_fee,
            ]),
            'chart' => $chart,
        ]);
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
}
