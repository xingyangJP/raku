<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RequirementDocumentChecker
{
    /**
     * Determine whether any of the provided items require a design/development attachment.
     */
    public function requiresDesignOrDevelopmentAttachment(array $items): bool
    {
        if (empty($items)) {
            return false;
        }

        $requiredCategoryCodes = ['B', 'C'];
        $productIds = collect($items)
            ->map(fn ($item) => data_get($item, 'product_id'))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $productCodes = collect($items)
            ->map(fn ($item) => data_get($item, 'code') ?? data_get($item, 'product_code'))
            ->map(fn ($value) => is_string($value) ? trim($value) : '')
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
        $productNames = collect($items)
            ->map(fn ($item) => data_get($item, 'name'))
            ->map(fn ($value) => is_string($value) ? trim($value) : '')
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();

        $categoryByProductId = [];
        $categoryByProductCode = [];
        $categoryByProductName = [];
        if (( !empty($productIds) || !empty($productCodes) || !empty($productNames))
            && Schema::hasTable('products')
            && Schema::hasTable('categories')
        ) {
            $rows = DB::table('products')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->where(function ($query) use ($productIds, $productCodes, $productNames) {
                    $applied = false;
                    if (!empty($productIds)) {
                        $query->whereIn('products.id', $productIds);
                        $applied = true;
                    }
                    if (!empty($productCodes)) {
                        $method = $applied ? 'orWhereIn' : 'whereIn';
                        $query->{$method}('products.sku', $productCodes);
                        $applied = true;
                    }
                    if (!empty($productNames)) {
                        $method = $applied ? 'orWhereIn' : 'whereIn';
                        $query->{$method}('products.name', $productNames);
                    }
                })
                ->get([
                    'products.id as id',
                    'products.sku as sku',
                    'products.name as name',
                    'categories.name as category_name',
                    'categories.code as category_code',
                ]);

            foreach ($rows as $row) {
                $payload = [
                    'name' => $row->category_name ?? null,
                    'code' => $row->category_code ?? null,
                ];
                if ($row->id !== null) {
                    $categoryByProductId[(int) $row->id] = $payload;
                }
                if (!empty($row->sku)) {
                    $categoryByProductCode[(string) $row->sku] = $payload;
                }
                if (!empty($row->name)) {
                    $categoryByProductName[(string) $row->name] = $payload;
                }
            }
        }

        foreach ($items as $item) {
            $categoryInfo = null;
            $productId = data_get($item, 'product_id');
            if (is_numeric($productId)) {
                $categoryInfo = $categoryByProductId[(int) $productId] ?? null;
            }
            if (!$categoryInfo) {
                $code = data_get($item, 'code') ?? data_get($item, 'product_code');
                if (is_string($code) && $code !== '') {
                    $categoryInfo = $categoryByProductCode[$code] ?? null;
                }
            }
            if (!$categoryInfo) {
                $name = data_get($item, 'name');
                if (is_string($name) && $name !== '') {
                    $categoryInfo = $categoryByProductName[$name] ?? null;
                }
            }
            if ($categoryInfo) {
                $categoryCode = strtoupper(trim((string) ($categoryInfo['code'] ?? '')));
                if ($categoryCode !== '' && in_array($categoryCode, $requiredCategoryCodes, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
