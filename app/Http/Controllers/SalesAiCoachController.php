<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\SalesAiCoachSession;
use App\Models\SalesAiCoachSetting;
use Inertia\Inertia;

class SalesAiCoachController extends Controller
{
    private array $defaultQuestions = [
        [
            'title' => 'ゴールの具体化',
            'body' => '今回の訪問で何を決めたいか？成功とみなせる状態や期限は？',
            'keywords' => ['ゴール', '目的', '成功', '期限'],
        ],
        [
            'title' => '不明点の洗い出し',
            'body' => 'ゴール達成のために、まだ分からないこと・確認したいことは何か？',
            'keywords' => ['不明', '確認', '質問'],
        ],
        [
            'title' => '関係者・影響範囲',
            'body' => '誰が関わり、誰に影響があるか？意思決定者・利用者・周辺部署は？',
            'keywords' => ['関係者', '影響', '意思決定'],
        ],
        [
            'title' => '現状と課題',
            'body' => '今のやり方や課題は何か？どこを変えたいか？',
            'keywords' => ['現状', '課題', '困りごと'],
        ],
        [
            'title' => '制約・前提',
            'body' => '予算・期限・利用できるリソースや既存ルールなどの制約は？',
            'keywords' => ['制約', '前提', '予算', '期限'],
        ],
        [
            'title' => '次アクションと担当',
            'body' => '今日決めることと、持ち帰り事項の担当者・期限は？',
            'keywords' => ['次回', 'アクション', '期限', '担当'],
        ],
    ];

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'goal' => ['required', 'string', 'max:1000'],
            'context' => ['nullable', 'string', 'max:2000'],
        ]);

        [$resolvedContext, $contextFetchMessage] = $this->resolveContext($validated['context'] ?? null);
        $sessionPayload = $validated + ['context' => $resolvedContext];

        $fallback = $this->computeQuestions(($validated['goal'] ?? '') . ' ' . ($resolvedContext ?? ''));
        $isFallback = true;
        $baseMessage = $contextFetchMessage;
        $message = $baseMessage;
        $questions = $fallback;

        try {
            $config = $this->resolveOpenAiConfig();
        } catch (\RuntimeException $e) {
            $message = trim(($baseMessage ? $baseMessage . ' ' : '') . $e->getMessage());
            $this->storeSession($request, $sessionPayload, $questions, $isFallback, $message);
            return response()->json([
                'questions' => $questions,
                'message' => $message,
                'fallback' => $isFallback,
            ], 200);
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt(),
            ],
            [
                'role' => 'user',
                'content' => "今日のゴール:\n{$validated['goal']}\n\n議事録/補足:\n" . ($resolvedContext ?? ''),
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $config['api_key'],
                'Content-Type' => 'application/json',
            ])
                ->timeout(20)
                ->post($config['base_url'] . '/v1/chat/completions', [
                    'model' => $config['model'],
                    'messages' => $messages,
                    'temperature' => 0.5,
                    'max_tokens' => 600,
                ]);
        } catch (\Throwable $e) {
            Log::warning('SalesAiCoach generate failed', ['exception' => $e->getMessage()]);
            $message = trim(($baseMessage ? $baseMessage . ' ' : '') . 'AI生成に失敗したためテンプレを表示しました。');
            $this->storeSession($request, $sessionPayload, $questions, $isFallback, $message, $messages);
            return response()->json([
                'questions' => $questions,
                'message' => $message,
                'fallback' => $isFallback,
            ], 200);
        }

        if (!$response->successful()) {
            Log::warning('SalesAiCoach AI error', ['status' => $response->status(), 'body' => $response->body()]);
            $message = trim(($baseMessage ? $baseMessage . ' ' : '') . 'AI生成に失敗したためテンプレを表示しました。');
            $this->storeSession($request, $sessionPayload, $questions, $isFallback, $message, $messages, $response->body());
            return response()->json([
                'questions' => $questions,
                'message' => $message,
                'fallback' => $isFallback,
            ], 200);
        }

        $raw = data_get($response->json(), 'choices.0.message.content', '');
        $parsed = $this->decodeAiJson($raw);
        $aiQuestions = collect($parsed['questions'] ?? [])->map(function ($q) {
            return [
                'title' => $q['title'] ?? '質問',
                'body' => $q['body'] ?? '',
            ];
        })->filter(fn($q) => trim($q['body']) !== '')->values()->all();
        $aiDo = collect(data_get($parsed, 'actions.do', []))->map(fn($v) => trim((string) $v))->filter()->values()->all();
        $aiDont = collect(data_get($parsed, 'actions.dont', []))->map(fn($v) => trim((string) $v))->filter()->values()->all();

        if (empty($aiQuestions)) {
            $message = trim(($baseMessage ? $baseMessage . ' ' : '') . 'AI応答の解析に失敗したためテンプレを表示しました。');
            $this->storeSession($request, $sessionPayload, $questions, $isFallback, $message, $messages, $raw);
            return response()->json([
                'questions' => $questions,
                'message' => $message,
                'fallback' => $isFallback,
            ], 200);
        }

        $questions = $aiQuestions;
        $isFallback = false;
        $message = $baseMessage; // contextFetchMessage があればそのまま返す（警告表示用）

        $this->storeSession($request, $sessionPayload, $questions, $isFallback, $message, $messages, $raw);

        return response()->json([
            'questions' => $questions,
            'do' => $aiDo,
            'dont' => $aiDont,
            'fallback' => $isFallback,
            'message' => $message,
        ]);
    }

    private function resolveContext(?string $context): array
    {
        if ($context === null || trim($context) === '') {
            return [null, null];
        }

        $url = trim($context);
        if (!Str::startsWith($url, ['http://', 'https://']) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return [$context, null];
        }

        try {
            $response = Http::timeout(5)->get($url);
            if (!$response->successful()) {
                return [$context, '権限がないかなんらかの原因でURLから情報を読めません。'];
            }
            $body = $response->body();
            $text = trim(mb_substr(strip_tags($body), 0, 20000));
            if ($text === '') {
                return [$context, '権限がないかなんらかの原因でURLから情報を読めません。'];
            }
            return [$text, null];
        } catch (\Throwable $e) {
            Log::info('SalesAiCoach context fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return [$context, '権限がないかなんらかの原因でURLから情報を読めません。'];
        }
    }

    private function resolveOpenAiConfig(): array
    {
        $apiKey = (string) config('services.openai.key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('OpenAI APIキーが未設定のためAI生成できません。');
        }

        return [
            'api_key' => $apiKey,
            'base_url' => rtrim((string) config('services.openai.base_url', 'https://api.openai.com'), '/'),
            'model' => (string) config('services.openai.model', 'gpt-4o-mini'),
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
        }
        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        $jsonStart = strpos($trimmed, '{');
        $jsonEnd = strrpos($trimmed, '}');
        if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
            $maybe = substr($trimmed, $jsonStart, $jsonEnd - $jsonStart + 1);
            $decoded = json_decode($maybe, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }

    private function computeQuestions(string $text): array
    {
        $haystack = mb_strtolower($text);
        $scored = collect($this->defaultQuestions)->map(function ($q, $idx) use ($haystack) {
            $keywords = $q['keywords'] ?? [];
            $hits = 0;
            foreach ($keywords as $kw) {
                if (str_contains($haystack, mb_strtolower($kw))) {
                    $hits++;
                }
            }
            return $q + ['score' => $hits, 'order' => $idx];
        })->sort(function ($a, $b) {
            if ($b['score'] === $a['score']) {
                return $a['order'] <=> $b['order'];
            }
            return $b['score'] <=> $a['score'];
        })->values();

        $filtered = $scored->filter(fn($q) => $q['score'] > 0);
        $base = $filtered->count() >= 5 ? $filtered : $scored;
        return $base->map(function ($q) {
            return ['title' => $q['title'], 'body' => $q['body']];
        })->values()->all();
    }

    private function storeSession(Request $request, array $validated, array $questions, bool $fallback, ?string $message = null, ?array $promptMessages = null, $rawResponse = null): void
    {
        try {
            SalesAiCoachSession::create([
                'user_id' => optional($request->user())->id,
                'goal' => $validated['goal'],
                'context' => $validated['context'] ?? null,
                'questions' => $questions,
                'fallback' => $fallback,
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            Log::warning('SalesAiCoach session store failed', [
                'exception' => $e->getMessage(),
                'goal' => $validated['goal'],
            ]);
        }
    }

    public function settings()
    {
        $this->authorizeManager();
        $setting = SalesAiCoachSetting::latest()->first();
        return Inertia::render('SalesAiCoach/Settings', [
            'basePrompt' => $setting?->base_prompt ?? '',
            'defaultPrompt' => $this->defaultSystemPrompt(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $this->authorizeManager();
        $validated = $request->validate([
            'base_prompt' => ['nullable', 'string', 'max:5000'],
        ]);

        SalesAiCoachSetting::create([
            'user_id' => optional($request->user())->id,
            'base_prompt' => $validated['base_prompt'] ?? null,
        ]);

        return back()->with('success', 'ベースプロンプトを更新しました。');
    }

    private function buildSystemPrompt(): string
    {
        $custom = SalesAiCoachSetting::latest()->value('base_prompt');
        $base = $this->defaultSystemPrompt();
        if ($custom && trim($custom) !== '') {
            return $base . ' Additional instructions: ' . $custom;
        }
        return $base;
    }

    private function defaultSystemPrompt(): string
    {
        return implode(' ', [
            'You are a Japanese presales assistant for enterprise sales.',
            'Return only JSON: {"questions":[{"title":"...","body":"..."}],"actions":{"do":["..."],"dont":["..."]}}.',
            '"actions" should list concrete things to do / not do today based on goal/context; keep them short bullet strings.',
            'Context: this is for a sales/discovery meeting about a sales management system.',
            'Canonical angles you should prioritize based on goal/context hints:',
            '1) Reports/printing: report types, layout, printer/paper size, Excel/CSV output.',
            '2) Workflow/permissions: approval routes, exceptions, delegate, notifications, audit log.',
            '3) Inventory/purchasing: inbound/outbound, lot/expiry, drop-ship, returns, stocktake, reorder point/lead time.',
            '4) Billing/AR/accounting: tax/rounding, billing/closing/invoice, payment matching, MF integration, journal implications.',
            '5) Non-functional/operations: concurrent users, response, backup/HA, monitoring, audit/retention.',
            'If the user text suggests reports/printing, always include a question on layout/printer/paper/output.',
            'If it suggests workflow/web, include approval/permissions/notifications.',
            'If it suggests inventory, include lot/stocktake/drop-ship/returns.',
            'If it suggests billing/accounting, include tax/closing/payment matching/MF link.',
            'If the goal is vague, first ask to narrow scope using these angles.',
            'Number of questions can be as many as needed; include clarifying questions if the goal/context is vague.',
            'Derive questions strictly from the goal/context the user wrote. Do not assume specific products or domains.',
            'If the goal is vague, ask clarifying, open-ended questions to make it concrete.',
            'No markdown, no code fences.',
        ]);
    }

    private function authorizeManager(): void
    {
        $user = optional(request()->user());
        $allowed = ['守部幸洋', '川口大希'];
        if (!$user || !in_array($user->name, $allowed, true)) {
            abort(403, 'You are not allowed to update AI coach settings.');
        }
    }
}
