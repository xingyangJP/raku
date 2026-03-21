<?php

namespace App\Services;

use App\Models\DashboardAiAnalysis;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DashboardAiAnalysisService
{
    private ?bool $supportsOverviewColumn = null;

    public function resolveOverall(array $metrics, Carbon $today): array
    {
        $selectedYear = (int) Arr::get($metrics, 'filters.selected_year', $today->year);
        $selectedMonth = (int) Arr::get($metrics, 'filters.selected_month', $today->month);
        $analysisDate = $today->copy()->startOfDay();

        $record = DashboardAiAnalysis::query()
            ->whereDate('analysis_date', $analysisDate->toDateString())
            ->where('target_year', $selectedYear)
            ->where('target_month', $selectedMonth)
            ->where('section_key', 'overall')
            ->first();

        if ($record && is_array($record->analysis_items) && count($record->analysis_items) > 0) {
            return [
                'items' => $record->analysis_items,
                'meta' => [
                    'source' => 'ai',
                    'status' => $record->status,
                    'generated_at' => optional($record->generated_at)->toIso8601String(),
                    'generated_at_label' => optional($record->generated_at)?->setTimezone(config('app.sales_timezone', config('app.timezone', 'Asia/Tokyo')))->format('Y年n月j日 H:i'),
                    'model' => $record->model,
                ],
                'overview' => $this->normalizeOverview($record->analysis_overview, $record->analysis_items),
            ];
        }

        if ($record && in_array($record->status, ['failed', 'skipped'], true)) {
            $fallbackItems = Arr::get($metrics, 'sections.overall.analysis', Arr::get($metrics, 'analysis', []));

            return [
                'items' => $fallbackItems,
                'meta' => [
                    'source' => 'rule',
                    'status' => $record->status,
                    'generated_at' => optional($record->generated_at)->toIso8601String(),
                    'generated_at_label' => optional($record->generated_at)?->setTimezone(config('app.sales_timezone', config('app.timezone', 'Asia/Tokyo')))->format('Y年n月j日 H:i'),
                    'model' => $record->model,
                ],
                'overview' => $this->buildFallbackOverview($metrics, $fallbackItems),
            ];
        }

        return $this->generateAndPersistOverall($metrics, $analysisDate, $selectedYear, $selectedMonth);
    }

    private function generateAndPersistOverall(array $metrics, Carbon $analysisDate, int $selectedYear, int $selectedMonth): array
    {
        $fallbackItems = Arr::get($metrics, 'sections.overall.analysis', Arr::get($metrics, 'analysis', []));
        $config = $this->resolveOpenAiConfig();

        if ($config === null) {
            DashboardAiAnalysis::query()->updateOrCreate(
                [
                    'analysis_date' => $analysisDate->toDateString(),
                    'target_year' => $selectedYear,
                    'target_month' => $selectedMonth,
                    'section_key' => 'overall',
                ],
                $this->buildPersistencePayload([
                    'status' => 'skipped',
                    'model' => null,
                    'analysis_items' => null,
                    'analysis_overview' => null,
                    'prompt_payload' => null,
                    'response_payload' => null,
                    'error_message' => 'OpenAI APIキー未設定',
                    'generated_at' => now(),
                ])
            );

            return [
                'items' => $fallbackItems,
                'meta' => [
                    'source' => 'rule',
                    'status' => 'skipped',
                    'generated_at' => null,
                    'generated_at_label' => null,
                    'model' => null,
                ],
                'overview' => $this->buildFallbackOverview($metrics, $fallbackItems),
            ];
        }

        $promptPayload = $this->buildPromptPayload($metrics, $selectedYear, $selectedMonth);
        $requestPayload = [
            'model' => $config['model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a CFO-style management analyst for a Japanese SMB. Return JSON only. Format: {"overview":{"headline":"...","summary":"...","focus_points":["..."],"actions":["..."]},"items":[{"title":"...","body":"...","tone":"positive|neutral|negative"}]}. Write concise Japanese. Focus only on overall company analysis, not section-specific commentary. Return 3 or 4 items max. Each body must be concrete and action-oriented. overview.summary should explain what matters this month and what to improve next. focus_points and actions should each contain 2 or 3 short bullets. Do not use markdown.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($promptPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ],
            'temperature' => 0.2,
            'max_tokens' => 500,
            'response_format' => ['type' => 'json_object'],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $config['api_key'],
                'Content-Type' => 'application/json',
            ])->timeout(25)->post($config['base_url'] . '/v1/chat/completions', $requestPayload);
        } catch (\Throwable $e) {
            Log::warning('Dashboard AI analysis request failed', [
                'exception' => $e->getMessage(),
                'year' => $selectedYear,
                'month' => $selectedMonth,
            ]);

            $this->storeFailure($analysisDate, $selectedYear, $selectedMonth, $config['model'], json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $e->getMessage());

            return [
                'items' => $fallbackItems,
                'meta' => [
                    'source' => 'rule',
                    'status' => 'failed',
                    'generated_at' => null,
                    'generated_at_label' => null,
                    'model' => $config['model'],
                ],
                'overview' => $this->buildFallbackOverview($metrics, $fallbackItems),
            ];
        }

        if (!$response->successful()) {
            Log::warning('Dashboard AI analysis response failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'year' => $selectedYear,
                'month' => $selectedMonth,
            ]);

            $this->storeFailure($analysisDate, $selectedYear, $selectedMonth, $config['model'], json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $response->body());

            return [
                'items' => $fallbackItems,
                'meta' => [
                    'source' => 'rule',
                    'status' => 'failed',
                    'generated_at' => null,
                    'generated_at_label' => null,
                    'model' => $config['model'],
                ],
                'overview' => $this->buildFallbackOverview($metrics, $fallbackItems),
            ];
        }

        $rawContent = trim((string) Arr::get($response->json(), 'choices.0.message.content', ''));
        $decoded = $this->decodeAiJson($rawContent);
        $items = $this->normalizeItems($decoded['items'] ?? null);
        $overview = $this->normalizeOverview($decoded['overview'] ?? null, $items);

        if (count($items) === 0) {
            $this->storeFailure($analysisDate, $selectedYear, $selectedMonth, $config['model'], json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'AI response did not contain valid items');

            return [
                'items' => $fallbackItems,
                'meta' => [
                    'source' => 'rule',
                    'status' => 'failed',
                    'generated_at' => null,
                    'generated_at_label' => null,
                    'model' => $config['model'],
                ],
                'overview' => $this->buildFallbackOverview($metrics, $fallbackItems),
            ];
        }

        $record = DashboardAiAnalysis::query()->updateOrCreate(
            [
                'analysis_date' => $analysisDate->toDateString(),
                'target_year' => $selectedYear,
                'target_month' => $selectedMonth,
                'section_key' => 'overall',
            ],
            $this->buildPersistencePayload([
                'status' => 'completed',
                'model' => $config['model'],
                'analysis_items' => $items,
                'analysis_overview' => $overview,
                'prompt_payload' => json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'response_payload' => $rawContent,
                'error_message' => null,
                'generated_at' => now(),
            ])
        );

        return [
            'items' => $items,
            'meta' => [
                'source' => 'ai',
                'status' => 'completed',
                'generated_at' => optional($record->generated_at)->toIso8601String(),
                'generated_at_label' => optional($record->generated_at)?->setTimezone(config('app.sales_timezone', config('app.timezone', 'Asia/Tokyo')))->format('Y年n月j日 H:i'),
                'model' => $record->model,
            ],
            'overview' => $overview,
        ];
    }

    private function resolveOpenAiConfig(): ?array
    {
        $apiKey = (string) config('services.openai.key', '');
        if ($apiKey === '') {
            return null;
        }

        return [
            'api_key' => $apiKey,
            'base_url' => rtrim((string) config('services.openai.base_url', 'https://api.openai.com'), '/'),
            'model' => (string) config('services.openai.model', 'gpt-4o-mini'),
        ];
    }

    private function buildPromptPayload(array $metrics, int $selectedYear, int $selectedMonth): array
    {
        $overall = Arr::get($metrics, 'sections.overall', []);
        $currentBudget = Arr::get($overall, 'budget.current', []);
        $currentActual = Arr::get($overall, 'actual.current', []);
        $currentEffort = Arr::get($overall, 'effort.current', []);
        $currentCash = Arr::get($overall, 'cash_flow.current', []);
        $yoy = Arr::get($overall, 'year_over_year.current', []);
        $people = Arr::get($overall, 'people.summary', []);

        return [
            'period' => [
                'year' => $selectedYear,
                'month' => $selectedMonth,
                'label' => Arr::get($metrics, 'periods.current.label'),
            ],
            'basis' => Arr::only(Arr::get($metrics, 'basis', []), ['budget', 'actual', 'recognition', 'cash_rule']),
            'overall' => [
                'budget_sales' => (float) ($currentBudget['sales'] ?? 0),
                'actual_sales' => (float) ($currentActual['sales'] ?? 0),
                'budget_gross_profit' => (float) ($currentBudget['gross_profit'] ?? 0),
                'actual_gross_profit' => (float) ($currentActual['gross_profit'] ?? 0),
                'budget_net_cash' => (float) ($currentCash['net_budget'] ?? 0),
                'actual_net_cash' => (float) ($currentCash['net_actual'] ?? 0),
                'planned_effort_person_days' => (float) ($currentEffort['planned'] ?? 0),
                'effort_fill_rate' => (float) ($currentEffort['planned_fill_rate'] ?? 0),
                'unassigned_person_days' => (float) ($people['unassigned_person_days'] ?? 0),
                'high_load_count' => (int) ($people['high_load_count'] ?? 0),
                'available_people_count' => (int) ($people['available_people_count'] ?? 0),
            ],
            'year_over_year' => [
                'sales_rate' => (float) Arr::get($yoy, 'sales.rate', 0),
                'gross_profit_rate' => (float) Arr::get($yoy, 'gross_profit.rate', 0),
                'net_cash_delta' => (float) Arr::get($yoy, 'net_cash.delta', 0),
            ],
            'current_rule_alerts' => Arr::map(Arr::get($overall, 'alerts', []), function ($item) {
                return [
                    'title' => Arr::get($item, 'title'),
                    'detail' => Arr::get($item, 'detail'),
                    'tone' => Arr::get($item, 'tone'),
                ];
            }),
        ];
    }


    private function normalizeOverview(mixed $overview, array $items): array
    {
        if (!is_array($overview)) {
            return $this->buildOverviewFromItems($items);
        }

        $headline = trim((string) ($overview['headline'] ?? '今月の経営総評'));
        $summary = trim((string) ($overview['summary'] ?? ''));
        $focusPoints = $this->normalizeShortList($overview['focus_points'] ?? []);
        $actions = $this->normalizeShortList($overview['actions'] ?? []);

        if ($summary === '') {
            return $this->buildOverviewFromItems($items);
        }

        return [
            'headline' => mb_substr($headline, 0, 40),
            'summary' => mb_substr($summary, 0, 240),
            'focus_points' => array_slice($focusPoints, 0, 3),
            'actions' => array_slice($actions, 0, 3),
        ];
    }

    private function normalizeShortList(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($item) {
            $text = trim((string) $item);
            return $text === '' ? null : mb_substr($text, 0, 80);
        }, $items)));
    }

    private function buildFallbackOverview(array $metrics, array $fallbackItems): array
    {
        return $this->buildOverviewFromItems($fallbackItems, Arr::get($metrics, 'periods.current.label', '今月'));
    }

    private function buildOverviewFromItems(array $items, string $periodLabel = '今月'): array
    {
        $headline = $periodLabel . 'の経営総評';
        $summary = count($items) > 0
            ? implode(' ', array_map(fn ($item) => (string) ($item['body'] ?? ''), array_slice($items, 0, 2)))
            : '大きな変動は限定的です。売上差異、工数充足率、資金繰りの3点を確認してください。';
        $focusPoints = array_values(array_filter(array_map(fn ($item) => (string) ($item['title'] ?? ''), array_slice($items, 0, 3))));
        $actions = array_values(array_filter(array_map(function ($item) {
            $body = (string) ($item['body'] ?? '');
            return $body !== '' ? mb_substr($body, 0, 80) : null;
        }, array_slice($items, 0, 3))));

        return [
            'headline' => $headline,
            'summary' => mb_substr($summary, 0, 240),
            'focus_points' => array_slice($focusPoints, 0, 3),
            'actions' => array_slice($actions, 0, 3),
        ];
    }

    private function decodeAiJson(?string $content): ?array
    {
        if ($content === null) {
            return null;
        }

        $trimmed = trim($content);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?/i', '', $trimmed, 1);
            $trimmed = preg_replace('/```$/', '', $trimmed, 1);
            $trimmed = trim($trimmed);
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $trimmed, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function normalizeItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));
            $body = trim((string) ($item['body'] ?? ''));
            $tone = (string) ($item['tone'] ?? 'neutral');
            if ($title === '' || $body === '') {
                continue;
            }
            if (!in_array($tone, ['positive', 'neutral', 'negative'], true)) {
                $tone = 'neutral';
            }

            $normalized[] = [
                'title' => mb_substr($title, 0, 40),
                'body' => mb_substr($body, 0, 220),
                'tone' => $tone,
            ];
        }

        return array_slice($normalized, 0, 4);
    }

    private function storeFailure(Carbon $analysisDate, int $selectedYear, int $selectedMonth, ?string $model, ?string $promptPayload, ?string $error): void
    {
        DashboardAiAnalysis::query()->updateOrCreate(
            [
                'analysis_date' => $analysisDate->toDateString(),
                'target_year' => $selectedYear,
                'target_month' => $selectedMonth,
                'section_key' => 'overall',
            ],
            $this->buildPersistencePayload([
                'status' => 'failed',
                'model' => $model,
                'analysis_items' => null,
                'analysis_overview' => null,
                'prompt_payload' => $promptPayload,
                'response_payload' => null,
                'error_message' => $error,
                'generated_at' => now(),
            ])
        );
    }

    private function buildPersistencePayload(array $payload): array
    {
        if ($this->supportsOverviewColumn()) {
            return $payload;
        }

        unset($payload['analysis_overview']);

        return $payload;
    }

    private function supportsOverviewColumn(): bool
    {
        if ($this->supportsOverviewColumn !== null) {
            return $this->supportsOverviewColumn;
        }

        $this->supportsOverviewColumn = Schema::hasTable('dashboard_ai_analyses')
            && Schema::hasColumn('dashboard_ai_analyses', 'analysis_overview');

        return $this->supportsOverviewColumn;
    }
}
