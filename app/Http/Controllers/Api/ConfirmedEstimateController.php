<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Services\EstimateMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfirmedEstimateController extends Controller
{
    public function __construct(private readonly EstimateMetricsService $metrics)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'updated_since' => ['nullable', 'date'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 50);
        $productLookups = $this->metrics->buildProductLookups();

        $query = Estimate::query()
            ->where('is_order_confirmed', true)
            ->whereNull('mf_deleted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if (!empty($validated['updated_since'])) {
            $query->where('updated_at', '>=', $validated['updated_since']);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (Estimate $estimate) => $this->serializeEstimate($estimate, $productLookups))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Estimate $estimate): JsonResponse
    {
        if (!$estimate->is_order_confirmed || $estimate->mf_deleted_at !== null) {
            abort(404);
        }

        return response()->json([
            'data' => $this->serializeEstimate($estimate, $this->metrics->buildProductLookups(), true),
        ]);
    }

    private function serializeEstimate(Estimate $estimate, array $productLookups, bool $includeItems = false): array
    {
        $metrics = $this->metrics->buildMetrics($estimate, $productLookups);
        $payload = [
            'id' => $estimate->id,
            'estimate_number' => $estimate->estimate_number,
            'customer_name' => $estimate->customer_name,
            'client_id' => $estimate->client_id,
            'title' => $estimate->title,
            'status' => $estimate->status,
            'is_order_confirmed' => (bool) $estimate->is_order_confirmed,
            'issue_date' => $estimate->issue_date?->toDateString(),
            'due_date' => $estimate->due_date?->toDateString(),
            'start_date' => $estimate->start_date?->toDateString(),
            'delivery_date' => $estimate->delivery_date?->toDateString(),
            'staff_id' => $estimate->staff_id,
            'staff_name' => $estimate->staff_name,
            'subtotal_excluding_tax' => $metrics['subtotal_excluding_tax'],
            'tax_amount' => $metrics['tax_amount'],
            'total_amount' => $metrics['total_amount'],
            'effort_person_days' => $metrics['effort_person_days'],
            'updated_at' => $estimate->updated_at?->toIso8601String(),
        ];

        if ($includeItems) {
            $payload['items'] = collect($estimate->items ?? [])
                ->map(fn ($item) => $this->serializeItem(is_array($item) ? $item : [], $productLookups))
                ->values();
        }

        return $payload;
    }

    private function serializeItem(array $item, array $productLookups): array
    {
        $product = $this->metrics->resolveProduct($item, $productLookups);
        $businessDivision = $item['business_division'] ?? ($product->business_division ?? null);
        $lineEstimate = new Estimate([
            'items' => [$item],
            'total_amount' => null,
            'tax_amount' => null,
        ]);

        return [
            'product_id' => $item['product_id'] ?? null,
            'code' => $item['code'] ?? $item['sku'] ?? null,
            'name' => $item['name'] ?? $item['product_name'] ?? null,
            'quantity' => is_numeric($item['qty'] ?? $item['quantity'] ?? null) ? (float) ($item['qty'] ?? $item['quantity']) : 0.0,
            'unit' => $item['unit'] ?? ($product->unit ?? null),
            'unit_price' => is_numeric($item['price'] ?? $item['unit_price'] ?? null) ? (float) ($item['price'] ?? $item['unit_price']) : 0.0,
            'business_division' => $businessDivision,
            'line_subtotal_excluding_tax' => $this->metrics->calculateSubtotalExcludingTax($lineEstimate),
            'effort_person_days' => $this->metrics->calculateEffort($lineEstimate, $productLookups),
        ];
    }
}
