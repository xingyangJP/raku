<?php

namespace App\Http\Controllers;

use App\Models\Billing;
use App\Models\LocalInvoice;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class BusinessDivisionReportController extends Controller
{
    private const DIVISION_META = [
        'first_business' => ['label' => '第1種事業', 'number' => '1'],
        'second_business' => ['label' => '第2種事業', 'number' => '2'],
        'third_business' => ['label' => '第3種事業', 'number' => '3'],
        'fourth_business' => ['label' => '第4種事業', 'number' => '4'],
        'fifth_business' => ['label' => '第5種事業', 'number' => '5'],
        'sixth_business' => ['label' => '第6種事業', 'number' => '6'],
    ];

    public function index(Request $request)
    {
        $timezone = config('app.sales_timezone', 'Asia/Tokyo');
        $currentMonth = Carbon::now($timezone);
        $fromMonth = (string) $request->query('from', $currentMonth->format('Y-m'));
        $toMonth = (string) ($request->query('to') ?? $currentMonth->format('Y-m'));

        $periodStart = Carbon::createFromFormat('Y-m', $fromMonth, $timezone)->startOfMonth();
        $periodEnd = Carbon::createFromFormat('Y-m', $toMonth, $timezone)->endOfMonth();

        $monthKeys = $this->buildMonthKeys($periodStart, $periodEnd);

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

        $localItems = $localInvoices->map(function (LocalInvoice $invoice) {
            $items = is_array($invoice->items) ? $invoice->items : [];
            return [
                'billing_id' => $invoice->id,
                'billing_number' => $invoice->billing_number,
                'billing_date' => $invoice->billing_date ? Carbon::parse($invoice->billing_date) : null,
                'partner_name' => $invoice->customer_name,
                'title' => $invoice->title,
                'source' => 'local',
                'mf_billing_id' => $invoice->mf_billing_id,
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

        $combinedBillings = $mfEntries->values();
        foreach ($localItems as $entry) {
            $hasDuplicate = $entry['mf_billing_id'] && $combinedBillings->contains(function ($mfEntry) use ($entry) {
                return $mfEntry['source'] === 'mf' && !empty($entry['mf_billing_id']) && $mfEntry['mf_billing_id'] === $entry['mf_billing_id'];
            });
            if (!$hasDuplicate) {
                $combinedBillings = $combinedBillings->push($entry);
            }
        }

        $itemCodes = $combinedBillings->flatMap(function ($billing) {
            return collect($billing['items'])->pluck('code');
        })->filter()->unique()->values()->all();
        $itemNames = $combinedBillings->flatMap(function ($billing) {
            return collect($billing['items'])->pluck('name')->map(fn ($name) => trim((string) $name));
        })->filter()->unique()->values()->all();

        $productsBySku = collect();
        $productsByName = collect();

        if (!empty($itemCodes) || !empty($itemNames)) {
            $productQuery = Product::query()->select(['id', 'sku', 'name', 'business_division', 'cost']);
            $productQuery->where(function ($query) use ($itemCodes, $itemNames) {
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
            });

            $products = $productQuery->get();
            $productsBySku = $products->filter(fn ($product) => !empty($product->sku))->keyBy('sku');
            $productsByName = $products->filter(fn ($product) => !empty($product->name))->keyBy('name');
        }

        $divisionLabels = $this->divisionLabels();
        $divisionKeys = array_keys($divisionLabels);
        $monthlyTotals = [];
        foreach ($monthKeys as $key) {
            $monthlyTotals[$key] = array_fill_keys($divisionKeys, 0);
            $monthlyTotals[$key]['total'] = 0;
        }

        $divisionTotals = array_fill_keys($divisionKeys, 0);
        $grandTotal = 0;
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
                if ($amount === 0) {
                    continue;
                }
                $product = null;
                $itemCode = $item['code'] ?? null;
                $itemName = $item['name'] ?? null;
                if (!empty($itemCode) && $productsBySku->has($itemCode)) {
                    $product = $productsBySku->get($itemCode);
                } elseif (!empty($itemName) && $productsByName->has($itemName)) {
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
                    'item_name' => $itemName ?? '項目',
                    'customer_name' => $billing['partner_name'] ?? '（顧客未設定）',
                    'detail' => $item['detail'] ?? '',
                    'quantity' => (float) ($item['quantity'] ?? 0),
                    'amount' => $amount,
                    'gross_profit' => $amount - $costAmount,
                    'product_id' => $product->id ?? null,
                    'product_sku' => $product->sku ?? $itemCode,
                ];
            }
        }

        $monthlyRows = array_map(function (string $monthKey) use ($monthlyTotals, $divisionLabels, $timezone) {
            $display = Carbon::createFromFormat('Y-m', $monthKey, $timezone)->format('Y年n月');
            return [
                'month' => $monthKey,
                'label' => $display,
                'divisions' => Arr::only($monthlyTotals[$monthKey], array_keys($divisionLabels)),
                'total' => $monthlyTotals[$monthKey]['total'],
            ];
        }, $monthKeys);

        return Inertia::render('BusinessDivisions/Summary', [
            'filters' => [
                'from' => $fromMonth,
                'to' => $toMonth,
            ],
            'divisionLabels' => $divisionLabels,
            'monthlyData' => $monthlyRows,
            'divisionTotals' => $divisionTotals,
            'grandTotal' => $grandTotal,
            'detailRows' => $detailRows,
            'businessDivisionOptions' => config('business_divisions.options', []),
        ]);
    }

    public function updateProductDivision(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'business_division' => ['required', 'string', 'in:' . implode(',', array_keys(config('business_divisions.options', [])))],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->with('error', '事業区分の更新に失敗しました。');
        }

        $division = $validator->validated()['business_division'];

        $product->business_division = $division;
        $product->save();

        return redirect()->back()->with('success', "{$product->name} の事業区分を更新しました。");
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

    private function divisionLabels(): array
    {
        return collect(self::DIVISION_META)->mapWithKeys(function ($meta, $key) {
            return [$key => $meta['label']];
        })->put('unclassified', '未分類')->all();
    }

    private function divisionMetaFor(string $key): array
    {
        if (isset(self::DIVISION_META[$key])) {
            return self::DIVISION_META[$key];
        }

        return ['label' => '未分類', 'number' => ''];
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
}
