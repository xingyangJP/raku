<?php

namespace App\Services;

class EstimateItemAssignmentNormalizer
{
    public function normalizeItems(array $items): array
    {
        return array_map(function ($item) {
            if (!is_array($item)) {
                return $item;
            }

            $item['assignees'] = $this->normalizeAssignees($item['assignees'] ?? []);

            return $item;
        }, $items);
    }

    /**
     * Normalize assignee shares to a stable 100% total.
     *
     * @param  array<int, mixed>  $assignees
     * @return array<int, array<string, mixed>>
     */
    public function normalizeAssignees(array $assignees): array
    {
        $normalized = collect($assignees)
            ->filter(fn ($assignee) => is_array($assignee))
            ->map(function (array $assignee) {
                $userId = $assignee['user_id'] ?? null;
                $userId = $userId === null ? null : trim((string) $userId);
                $userId = $userId === '' ? null : $userId;

                $userName = trim((string) ($assignee['user_name'] ?? ''));
                $sharePercent = $assignee['share_percent'] ?? null;
                $sharePercent = is_numeric($sharePercent) ? max(0, (float) $sharePercent) : null;

                return [
                    'user_id' => $userId,
                    'user_name' => $userName !== '' ? $userName : null,
                    'share_percent' => $sharePercent,
                ];
            })
            ->filter(fn (array $assignee) => $assignee['user_id'] !== null || $assignee['user_name'] !== null)
            ->values();

        if ($normalized->isEmpty()) {
            return [];
        }

        $weights = $normalized
            ->map(fn (array $assignee) => $assignee['share_percent'] ?? 0.0)
            ->map(fn (float $share) => $share > 0 ? $share : 0.0)
            ->all();

        $total = array_sum($weights);
        if ($total <= 0) {
            $weights = array_fill(0, $normalized->count(), 1.0);
            $total = (float) count($weights);
        }

        $remaining = 100.0;

        return $normalized
            ->map(function (array $assignee, int $index) use ($weights, $total, &$remaining, $normalized) {
                if ($index === $normalized->count() - 1) {
                    $sharePercent = $this->roundToOneDecimal(max(0, $remaining));
                } else {
                    $sharePercent = $this->roundToOneDecimal(($weights[$index] / $total) * 100);
                    $remaining = $this->roundToOneDecimal($remaining - $sharePercent);
                }

                return [
                    'user_id' => $assignee['user_id'],
                    'user_name' => $assignee['user_name'],
                    'share_percent' => $sharePercent,
                ];
            })
            ->all();
    }

    private function roundToOneDecimal(float $value): float
    {
        return round($value, 1);
    }
}
