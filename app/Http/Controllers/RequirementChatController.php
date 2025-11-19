<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use App\Models\RequirementChatMessage;
use App\Models\RequirementChatThread;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RequirementChatController extends Controller
{
    public function show(Estimate $estimate)
    {
        $thread = $this->getOrCreateThread($estimate);
        $thread->load('messages');
        return response()->json([
            'thread_id' => $thread->id,
            'messages' => $thread->messages,
        ]);
    }

    public function store(Request $request, Estimate $estimate)
    {
        $validated = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $thread = $this->getOrCreateThread($estimate);

        $msg = new RequirementChatMessage([
            'role' => 'user',
            'content' => $validated['message'],
            'meta' => [
                'user_id' => Auth::id(),
            ],
        ]);

        $thread->messages()->save($msg);

        try {
            $assistantReply = $this->callAi($thread);
            $thread->messages()->create([
                'role' => 'assistant',
                'content' => $assistantReply,
                'meta' => ['source' => 'ai'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Requirement chat AI call failed', [
                'thread_id' => $thread->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'thread_id' => $thread->id,
            'messages' => $thread->messages()->get(),
        ], 202);
    }

    public function draft(Request $request)
    {
        $validated = $request->validate([
            'messages' => ['required', 'array'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant,system'],
            'messages.*.content' => ['required', 'string'],
        ]);

        try {
            $reply = $this->callAiFromHistory($validated['messages']);
            return response()->json([
                'status' => 'ok',
                'assistant' => $reply,
            ]);
        } catch (\Throwable $e) {
            Log::error('Requirement chat draft AI call failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'AI応答の生成に失敗しました。',
            ], 500);
        }
    }

    public function importDraft(Request $request, Estimate $estimate)
    {
        $validated = $request->validate([
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string'],
        ]);

        $thread = $this->getOrCreateThread($estimate);

        foreach ($validated['messages'] as $message) {
            $meta = $message['role'] === 'user'
                ? ['user_id' => Auth::id()]
                : ['source' => 'ai'];

            $thread->messages()->create([
                'role' => $message['role'],
                'content' => $message['content'],
                'meta' => $meta,
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'thread_id' => $thread->id,
            'messages' => $thread->messages()->orderBy('created_at')->get(),
        ], 201);
    }

    private function getOrCreateThread(Estimate $estimate): RequirementChatThread
    {
        return RequirementChatThread::firstOrCreate(
            ['estimate_id' => $estimate->id],
            ['user_id' => Auth::id()]
        );
    }

    private function callAi(RequirementChatThread $thread): string
    {
        $history = $thread->messages()->orderBy('created_at')->get()->map(function ($m) {
            return [
                'role' => $m->role,
                'content' => $m->content,
            ];
        })->toArray();

        return $this->callAiFromHistory($history);
    }

    private function callAiFromHistory(array $history): string
    {
        $apiKey = (string) config('services.openai.key', '');
        if ($apiKey === '') {
            return '【AI未実行】APIキーが設定されていません。';
        }
        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com'), '/');
        $model = (string) config('services.openai.model', 'gpt-4o-mini');

        $systemPrompt = "あなたは要件整理を行うアシスタントです。重要ルール:\n"
            . "1) 入力が不足していれば、最初に確認したい質問リストを箇条書きで提示する。\n"
            . "2) 既知情報だけで仮に整理する場合は「これは仮の前提です」「今後要確認」と明示する。\n"
            . "3) 出力は指定のMarkdown章立てに沿うこと（AI_ESTIMATE.mdのフォーマット）。\n"
            . "Markdownは章立てを守り、各章に不足情報・仮置きを必ず明示してください。";

        $messages = array_merge([
            ['role' => 'system', 'content' => $systemPrompt],
        ], $history);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->post($baseUrl . '/v1/chat/completions', [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.4,
            'max_tokens' => 1200,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('AI response failed: ' . $response->status());
        }

        return (string) data_get($response->json(), 'choices.0.message.content', '応答を生成できませんでした。');
    }
}
