<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\LocalInvoice;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class BusinessDivisionAnalysisService
{
    private const DIVISION_META = [
        'first_business' => ['label' => '第1種事業', 'number' => '1'],
        'second_business' => ['label' => '第2種事業', 'number' => '2'],
        'third_business' => ['label' => '第3種事業', 'number' => '3'],
        'fourth_business' => ['label' => '第4種事業', 'number' => '4'],
        'fifth_business' => ['label' => '第5種事業', 'number' => '5'],
        'sixth_business' => ['label' => '第6種事業', 'number' => '6'],
    ];

    public function buildForYear(int $year, int $focusMonth, string $timezone = 'Asia/Tokyo'): array
    {
        $periodStart = Carbon::create($year, 1, 1, 0, 0, 0, $timezone)->startOfMonth();
        $periodEnd = Carbon::create($year, 12, 1, 0, 0, 0, $timezone)->endOfMonth();
        $monthKeys = $this->buildMonthKeys($periodStart, $periodEnd);

        $combinedBillings = $this->collectBillingSources($periodStart, $periodEnd);
        $products = $this->resolveProductsForBillings($combinedBillings);
        [$productsBySku, $productsByName] = $products;

        $divisionLabels = $this->divisionLabels();
        $divisionKeys = array_keys($divisionLabels);
        $monthlyTotals = [];
        foreach ($monthKeys as $key) {
            $monthlyTotals[$key] = array_fill_keys($divisionKeys, 0.0);
            $monthlyTotals[$key]['total'] = 0.0;
        }

        $divisionTotals = array_fill_keys($divisionKeys, 0.0);
        $grandTotal = 0.0;
        $detailRows = [];

        foreach ($combinedBillings as $billing) {
            $billingDate = $billing['billing_date'] ?? null;
            if (!$billingDate) {
                continue;
            }

            $billingMonth = Carbon::parse($billingDate, $timezone)->format('Y-m');
            if (!isset($monthlyTotals[$billingMonth])) {
                continue;
            }

            foreach ($billing['items'] as $item) {
                $amount = $this->calculateItemAmount($item);
                if ($amount === 0.0) {
                    continue;
                }

                $product = null;
                $itemCode = $item['code'] ?? null;
                $itemName = trim((string) ($item['name'] ?? ''));
                if (!empty($itemCode) && $productsBySku->has($itemCode)) {
                    $product = $productsBySku->get($itemCode);
                } elseif ($itemName !== '' && $productsByName->has($itemName)) {
                    $product = $productsByName->get($itemName);
                }

                $divisionKey = $product?->business_division ?: 'unclassified';
                $divisionMeta = $this->divisionMetaFor($divisionKey);
                $costAmount = $this->calculateCostAmount($item, $product);

                $monthlyTotals[$billingMonth][$divisionKey] += $amount;
                $monthlyTotals[$billingMonth]['total'] += $amount;
                $divisionTotals[$divisionKey] += $amount;
                $grandTotal += $amount;

                $detailRows[] = [
                    'month' => $billingMonth,
                    'month_label' => Carbon::createFromFormat('Y-m', $billingMonth, $timezone)->format('Y年n月'),
                    'division_key' => $divisionKey,
                    'division_label' => $divisionMeta['label'],
                    'division_number' => $divisionMeta['number'],
                    'item_name' => $itemName !== '' ? $itemName : '項目',
                    'customer_name' => $billing['partner_name'] ?? '（顧客未設定）',
                    'detail' => $item['detail'] ?? '',
                    'quantity' => (float) ($item['quantity'] ?? 0),
                    'amount' => $amount,
                    'gross_profit' => $amount - $costAmount,
                ];
            }
        }

        $focusMonthKey = sprintf('%04d-%02d', $year, max(1, min(12, $focusMonth)));
        $focusMonthLabel = Carbon::createFromFormat('Y-m', $focusMonthKey, $timezone)->format('Y年n月');
        $focusDetails = collect($detailRows)
            ->filter(fn (array $row) => $row['month'] === $focusMonthKey)
            ->sortByDesc('amount')
            ->values()
            ->all();

        $monthlyRows = array_map(function (string $monthKey) use ($monthlyTotals, $divisionLabels, $timezone) {
            return [
                'month' => $monthKey,
                'label' => Carbon::createFromFormat('Y-m', $monthKey, $timezone)->format('Y年n月'),
                'divisions' => Arr::only($monthlyTotals[$monthKey], array_keys($divisionLabels)),
                'total' => $monthlyTotals[$monthKey]['total'],
            ];
        }, $monthKeys);

        return [
            'period' => [
                'year' => $year,
                'label' => "{$year}年",
                'focus_month' => $focusMonthKey,
                'focus_month_label' => $focusMonthLabel,
            ],
            'basis' => [
                'label' => '請求実績ベース',
                'detail' => 'Money Forward請求と自社請求の billing_date ベースで集計。商品マスタの事業区分設定を使用します。',
            ],
            'division_labels' => $divisionLabels,
            'monthly_data' => $monthlyRows,
            'division_totals' => $divisionTotals,
            'grand_total' => $grandTotal,
            'detail_rows' => $focusDetails,
        ];
    }

    private function collectBillingSources(Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $billings = Billing::query()
            ->with('items')
            ->whereBetween('billing_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereNull('mf_deleted_at')
            ->get(['id', 'billing_number', 'billing_date', 'partner_name', 'title']);

        $localInvoices = LocalInvoice::query()
            ->whereBetween('billing_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get(['id', 'billing_date', 'customer_name', 'title', 'billing_number', 'mf_billing_id', 'items']);

        $mfEntries = $billings->map(function (Billing $billing) {
            return [
                'source' => 'mf',
                'billing_id' => $billing->id,
                'mf_billing_id' => $billing->id,
                'billing_number' => $billing->billing_number,
                'billing_date' => $billing->billing_date,
                'partner_name' => $billing->partner_name,
                'title' => $billing->title,
                'items' => $billing->items->map->toArray()->all(),
            ];
        });

        $localEntries = $localInvoices->map(function (LocalInvoice $invoice) {
            $items = is_array($invoice->items) ? $invoice->items : [];

            return [
                'source' => 'local',
                'billing_id' => $invoice->id,
                'mf_billing_id' => $invoice->mf_billing_id,
                'billing_number' => $invoice->billing_number,
                'billing_date' => $invoice->billing_date ? Carbon::parse($invoice->billing_date) : null,
                'partner_name' => $invoice->customer_name,
                'title' => $invoice->title,
                'items' => array_map(fn ($item) => [
                    'name' => $item['name'] ?? '',
                    'detail' => $item['description'] ?? $item['detail'] ?? '',
                    'quantity' => $item['qty'] ?? $item['quantity'] ?? 0,
                    'price' => $item['price'] ?? 0,
                    'code' => $item['code'] ?? $item['product_code'] ?? null,
                    'cost' => $item['cost'] ?? null,
                ], $items),
            ];
        });

        $combined = $mfEntries->values();
        foreach ($localEntries as $entry) {
            $hasDuplicate = $entry['mf_billing_id'] && $combined->contains(function ($existing) use ($entry) {
                return $existing['source'] === 'mf'
                    && !empty($entry['mf_billing_id'])
                    && $existing['mf_billing_id'] === $entry['mf_billing_id'];
            });

            if (!$hasDuplicate) {
                $combined = $combined->push($entry);
            }
        }

        return $combined;
    }

    private function resolveProductsForBillings(Collection $combinedBillings): array
    {
        $itemCodes = $combinedBillings->flatMap(fn ($billing) => collect($billing['items'])->pluck('code'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $itemNames = $combinedBillings->flatMap(fn ($billing) => collect($billing['items'])->pluck('name')->map(fn ($name) => trim((string) $name)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($itemCodes) && empty($itemNames)) {
            return [collect(), collect()];
        }

        $products = Product::query()
            ->select(['id', 'sku', 'name', 'business_division', 'cost'])
            ->where(function ($query) use ($itemCodes, $itemNames) {
                $applied = false;
                if (!empty($itemCodes)) {
                    $query->whereIn('sku', $itemCodes);
                    $applied = true;
                }
                if (!empty($itemNames)) {
                    if ($applied) {
                        $query->orWhereIn('name', $itemNames);
                    } else {
                        $query->whereIn('name', $itemNames);
                    }
                }
            })
            ->get();

        return [
            $products->filter(fn ($product) => !empty($product->sku))->keyBy('sku'),
            $products->filter(fn ($product) => !empty($product->name))->keyBy('name'),
        ];
    }

    private function buildMonthKeys(Carbon $start, Carbon $end): array
    {
        $keys = [];
        $cursor = $start->copy();
        while ($cursor->lessThanOrEqualTo($end)) {
            $keys[] = $cursor->format('Y-m');
            $cursor->addMonthNoOverflow();
        }

        return $keys;
    }

    private function calculateItemAmount($item): float
    {
        $qty = (float) ($item['qty'] ?? $item['quantity'] ?? 0);
        $price = (float) ($item['price'] ?? 0);

        return round($qty * $price, 2);
    }

    private function calculateCostAmount(array $item, $product = null): float
    {
        $qty = (float) ($item['qty'] ?? $item['quantity'] ?? 0);
        $cost = $item['cost'] ?? null;

        if ($cost === null && $product) {
            $cost = $product->cost;
        }

        return round($qty * (float) ($cost ?? 0), 2);
    }

    private function divisionLabels(): array
    {
        return collect(self::DIVISION_META)
            ->mapWithKeys(fn ($meta, $key) => [$key => $meta['label']])
            ->put('unclassified', '未分類')
            ->all();
    }

    private function divisionMetaFor(string $key): array
    {
        if (isset(self::DIVISION_META[$key])) {
            return self::DIVISION_META[$key];
        }

        return ['label' => '未分類', 'number' => ''];
    }
}
