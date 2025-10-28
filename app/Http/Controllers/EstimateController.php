<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use App\Models\Partner;
use App\Models\User;
use App\Services\MoneyForwardApiService;
use App\Services\MoneyForwardQuoteSynchronizer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\StreamHandler;

class EstimateController extends Controller
{
    private function loadProducts()
    {
        if (Schema::hasTable('products')) {
            return DB::table('products')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'price', 'cost', 'unit', 'sku as code', 'description']);
        }
        return [];
    }

    public function index(Request $request, MoneyForwardQuoteSynchronizer $quoteSynchronizer)
    {
        $syncStatus = $quoteSynchronizer->syncIfStale($request->user()?->id);

        if (($syncStatus['status'] ?? null) === 'unauthorized') {
            $request->session()->put('mf_redirect_back', url()->full());
            $request->session()->put('mf_redirect_action', 'sync_quotes');
            return redirect()->route('quotes.auth.start');
        }

        if (($syncStatus['status'] ?? null) === 'error' && !$request->session()->has('error')) {
            $message = 'Money Forwardとの同期に失敗しました: ' . ($syncStatus['message'] ?? '理由不明');
            $request->session()->flash('error', $message);
        }

        $timezone = config('app.sales_timezone', 'Asia/Tokyo');
        $currentMonth = Carbon::now($timezone);
        $fromMonth = $request->query('from');
        $toMonth = $request->query('to');
        $partner = trim((string) $request->query('partner', ''));
        $status = trim((string) $request->query('status', ''));

        $estimatesQuery = Estimate::query()
            ->whereNull('mf_deleted_at')
            ->orderByDesc('issue_date')
            ->orderByDesc('estimate_number')
            ->orderByDesc('id');

        if ($fromMonth || $toMonth) {
            $issueStart = $fromMonth
                ? Carbon::createFromFormat('Y-m', $fromMonth, $timezone)->startOfMonth()
                : Carbon::create(1970, 1, 1, 0, 0, 0, $timezone);
            $issueEnd = $toMonth
                ? Carbon::createFromFormat('Y-m', $toMonth, $timezone)->endOfMonth()
                : Carbon::now($timezone)->endOfMonth();

            $estimatesQuery->where(function ($query) use ($issueStart, $issueEnd) {
                $query->whereBetween('issue_date', [$issueStart->toDateString(), $issueEnd->toDateString()])
                    ->orWhereNull('issue_date');
            });
        }

        if ($partner !== '') {
            $estimatesQuery->where('customer_name', 'like', '%' . $partner . '%');
        }

        if ($status !== '') {
            $estimatesQuery->where('status', $status);
        }

        $estimates = $estimatesQuery->get();

        $moneyForwardConfig = [
            'client_id' => config('services.money_forward.client_id'),
            'redirect_uri' => config('services.money_forward.quote_redirect_uri'),
            'authorization_url' => config('services.money_forward.authorization_url'),
            'scope' => 'mfc/invoice/data.read',
            'auth_start_route' => route('quotes.auth.start'),
        ];

        $defaultRange = [
            'from' => $fromMonth ?? $currentMonth->format('Y-m'),
            'to' => $toMonth ?? $currentMonth->format('Y-m'),
        ];

        $initialFilters = [
            'title' => (string) $request->query('title', ''),
            'partner' => $partner,
            'status' => $status,
            'from' => $fromMonth,
            'to' => $toMonth,
        ];

        return Inertia::render('Quotes/Index', [
            'estimates' => $estimates,
            'syncStatus' => $syncStatus,
            'moneyForwardConfig' => $moneyForwardConfig,
            'error' => session('error'),
            'defaultRange' => $defaultRange,
            'initialFilters' => $initialFilters,
        ]);
    }

    public function syncQuotes(Request $request, MoneyForwardQuoteSynchronizer $quoteSynchronizer)
    {
        $result = $quoteSynchronizer->sync($request->user()->id);

        if (($result['status'] ?? null) === 'unauthorized') {
            $redirectBack = url()->previous() ?: route('quotes.index');
            $request->session()->put('mf_redirect_back', $redirectBack);
            $request->session()->put('mf_redirect_action', 'sync_quotes');
            return redirect()->route('quotes.auth.start');
        }

        if (($result['status'] ?? null) === 'error') {
            $message = 'Money Forwardとの同期に失敗しました: ' . ($result['message'] ?? '理由不明');
            return redirect()->route('quotes.index')->with('error', $message);
        }

        if (($result['status'] ?? null) === 'skipped') {
            return redirect()->route('quotes.index')->with('info', 'Money Forwardの同期は現在実行中です。');
        }

        return redirect()->route('quotes.index')->with('success', 'Money Forwardの見積書を同期しました。');
    }

    public function redirectToAuthForQuoteSync(Request $request)
    {
        $request->session()->put('mf_redirect_action', 'sync_quotes');
        $redirectBack = $request->session()->get('mf_redirect_back', route('quotes.index'));
        $request->session()->put('mf_redirect_back', $redirectBack);

        $authUrl = config('services.money_forward.authorization_url') . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.money_forward.client_id'),
            'redirect_uri' => config('services.money_forward.redirect_uri'),
            'scope' => 'mfc/invoice/data.read',
        ]);

        return \Inertia\Inertia::location($authUrl);
    }

    public function handleQuoteSyncCallback(
        Request $request,
        MoneyForwardApiService $apiService,
        MoneyForwardQuoteSynchronizer $quoteSynchronizer
    ) {
        if (!$request->has('code')) {
            return redirect()->route('quotes.index')->with('error', 'Authorization code not found.');
        }

        try {
            $tokenData = $apiService->getAccessTokenFromCode($request->code, config('services.money_forward.quote_redirect_uri'));
            if (!$tokenData || empty($tokenData['access_token'])) {
                return redirect()->route('quotes.index')->with('error', 'Money Forwardの認証に失敗しました。');
            }

            $apiService->storeToken($tokenData, $request->user()->id);

            $result = $quoteSynchronizer->sync($request->user()->id);
            $redirectTo = $request->session()->pull('mf_redirect_back', route('quotes.index'));
            $request->session()->forget('mf_redirect_action');

            if (($result['status'] ?? null) === 'error') {
                $message = 'Money Forwardとの同期に失敗しました: ' . ($result['message'] ?? '理由不明');
                return redirect()->to($redirectTo)->with('error', $message);
            }

            return redirect()->to($redirectTo)->with('success', 'Money Forwardの見積書を同期しました。');
        } catch (\Exception $e) {
            Log::error('Money Forward quote sync callback failed', [
                'exception' => $e,
            ]);
            return redirect()->route('quotes.index')->with('error', 'Money Forward連携処理でエラーが発生しました。');
        }
    }

    public function create()
    {
        $products = $this->loadProducts();
        return Inertia::render('Estimates/Create', [
            'products' => $products,
        ]);
    }

    public function saveDraft(Request $request)
    {
        // This method's content is restored from previous versions.
        $clientId = $request->input('client_id');
        if (!is_null($clientId) && !is_string($clientId)) {
            $request->merge(['client_id' => (string) $clientId]);
        }

        if ($request->has('id') && $request->id) {
            $estimate = Estimate::findOrFail($request->id);
            if ($estimate->status !== 'draft') {
                return response()->json(['message' => 'Only drafts can be updated via saveDraft.'], 422);
            }
            $validated = $request->validate([
                'customer_name' => 'required|string|max:255',
                'client_id' => 'nullable|string|max:255',
                'mf_department_id' => 'nullable|string|max:255',
                'title' => 'required|string|max:255',
                'issue_date' => 'nullable|date',
                'due_date' => 'nullable|date',
                'total_amount' => 'required|integer',
                'tax_amount' => 'required|integer',
                'notes' => 'nullable|string',
                'internal_memo' => 'nullable|string',
                'delivery_location' => 'nullable|string',
                'items' => 'required|array|min:1',
                'estimate_number' => 'required|string|max:255|unique:estimates,estimate_number,' . $estimate->id,
                'staff_id' => 'nullable|integer',
                'staff_name' => 'required|string|max:255',
            ]);
            $estimate->update(array_merge($validated, ['status' => 'draft']));
            return redirect()->route('estimates.edit', $estimate->id)->with('success', 'Draft updated successfully.');
        } else {
            $validated = $request->validate([
                'customer_name' => 'required|string|max:255',
                'client_id' => 'nullable|string|max:255',
                'mf_department_id' => 'nullable|string|max:255',
                'title' => 'required|string|max:255',
                'issue_date' => 'nullable|date',
                'due_date' => 'nullable|date',
                'total_amount' => 'required|integer',
                'tax_amount' => 'required|integer',
                'notes' => 'nullable|string',
                'internal_memo' => 'nullable|string',
                'delivery_location' => 'nullable|string',
                'items' => 'required|array|min:1',
                'estimate_number' => 'nullable|string|max:255|unique:estimates,estimate_number',
                'staff_id' => 'nullable|integer',
                'staff_name' => 'required|string|max:255',
            ]);

            if (empty($validated['estimate_number'])) {
                $validated['estimate_number'] = Estimate::generateReadableEstimateNumber(
                    $validated['staff_id'] ?? null,
                    $validated['client_id'] ?? null,
                    true
                );
            }
            
            $estimate = Estimate::create(array_merge($validated, ['status' => 'draft']));
            return redirect()->route('estimates.edit', $estimate->id)->with('success', 'Draft saved successfully.');
        }
    }

    public function store(Request $request)
    {
        // This method's content is restored from previous versions.
        $clientId = $request->input('client_id');
        if (!is_null($clientId) && !is_string($clientId)) {
            $request->merge(['client_id' => (string) $clientId]);
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'client_id' => 'nullable|string|max:255',
            'mf_department_id' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'total_amount' => 'required|integer',
            'tax_amount' => 'required|integer',
            'notes' => 'nullable|string',
            'internal_memo' => 'nullable|string',
            'delivery_location' => 'nullable|string',
            'items' => 'required|array',
            'estimate_number' => 'nullable|string|max:255|unique:estimates,estimate_number',
            'staff_id' => 'nullable|integer',
            'staff_name' => 'nullable|string|max:255',
            'approval_flow' => 'nullable|array',
            'status' => 'nullable|string|in:draft,pending,sent,rejected',
        ]);

        if (!empty($validated['issue_date'])) {
            $validated['issue_date'] = date('Y-m-d', strtotime($validated['issue_date']));
        }
        if (!empty($validated['due_date'])) {
            $validated['due_date'] = date('Y-m-d', strtotime($validated['due_date']));
        }

        if (!Schema::hasColumn('estimates', 'approval_flow')) {
            unset($validated['approval_flow']);
        }

        if (empty($validated['estimate_number'])) {
            $validated['estimate_number'] = Estimate::generateReadableEstimateNumber(
                $validated['staff_id'] ?? null,
                $validated['client_id'] ?? null,
                false
            );
        }
        $status = $validated['status'] ?? 'sent';
        unset($validated['status']);
        if (isset($validated['approval_flow']) && is_array($validated['approval_flow'])) {
            $hasUnapproved = false;
            $normalizedFlow = [];
            foreach ($validated['approval_flow'] as $step) {
                $approvedAt = $status === 'pending' ? null : ($step['approved_at'] ?? null);
                if (empty($approvedAt)) { $hasUnapproved = true; }
                $normalizedFlow[] = [
                    'id' => $step['id'] ?? null,
                    'name' => $step['name'] ?? null,
                    'approved_at' => $approvedAt,
                    'status' => $approvedAt ? 'approved' : 'pending',
                ];
            }
            $validated['approval_flow'] = $normalizedFlow;
            if ($hasUnapproved && $status !== 'draft') { $status = 'pending'; }
            if (Schema::hasColumn('estimates', 'approval_started')) {
                $validated['approval_started'] = $hasUnapproved;
            }
        }
        $estimate = Estimate::create(array_merge($validated, ['status' => $status]));

        if ($status === 'pending') {
            $this->notifyApprovalRequested($estimate, Auth::user());
        }

        return redirect()->route('estimates.edit', $estimate->id)
            ->with('approval_success_message', '承認申請を開始しました。');
    }

    public function edit(Estimate $estimate)
    {
        $products = $this->loadProducts();
        $is_fully_approved = $estimate->status === 'sent';

        $estimate->customer = (object)[
            'id' => $estimate->client_id,
            'mf_partner_id' => $estimate->client_id,
            'customer_name' => $estimate->customer_name,
        ];

        return Inertia::render('Estimates/Create', [
            'estimate' => $estimate,
            'products' => $products,
            'is_fully_approved' => $is_fully_approved,
        ]);
    }

    public function update(Request $request, Estimate $estimate)
    {
        $clientId = $request->input('client_id');
        if (!is_null($clientId) && !is_string($clientId)) {
            $request->merge(['client_id' => (string) $clientId]);
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'client_id' => 'nullable|string|max:255',
            'mf_department_id' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'total_amount' => 'required|integer',
            'tax_amount' => 'required|integer',
            'notes' => 'nullable|string',
            'internal_memo' => 'nullable|string',
            'delivery_location' => 'nullable|string',
            'items' => 'required|array',
            'estimate_number' => 'required|string|max:255|unique:estimates,estimate_number,' . $estimate->id,
            'staff_id' => 'nullable|integer',
            'staff_name' => 'nullable|string|max:255',
            'approval_flow' => 'nullable|array',
            'status' => 'nullable|string|in:draft,pending,sent,rejected',
        ]);

        if (!empty($validated['issue_date'])) {
            $validated['issue_date'] = date('Y-m-d', strtotime($validated['issue_date']));
        }
        if (!empty($validated['due_date'])) {
            $validated['due_date'] = date('Y-m-d', strtotime($validated['due_date']));
        }

        $originalStatus = $estimate->status;
        $status = $validated['status'] ?? $estimate->status;
        unset($validated['status']);

        if (!Schema::hasColumn('estimates', 'approval_flow')) {
            unset($validated['approval_flow']);
        }

        if (isset($validated['approval_flow']) && is_array($validated['approval_flow'])) {
            $hasUnapproved = false;
            $normalizedFlow = [];
            foreach ($validated['approval_flow'] as $step) {
                $approvedAt = ($status === 'pending') ? null : ($step['approved_at'] ?? null);
                if (empty($approvedAt)) { $hasUnapproved = true; }
                $normalizedFlow[] = [
                    'id' => $step['id'] ?? null,
                    'name' => $step['name'] ?? null,
                    'approved_at' => $approvedAt,
                    'status' => $approvedAt ? 'approved' : 'pending',
                ];
            }
            $validated['approval_flow'] = $normalizedFlow;
            if ($hasUnapproved && $status !== 'draft') { $status = 'pending'; }
            if (Schema::hasColumn('estimates', 'approval_started')) {
                $validated['approval_started'] = ($status === 'pending');
            }
        } elseif ($status === 'pending' && is_array($estimate->approval_flow)) {
            $validated['approval_flow'] = array_map(function ($s) {
                return [
                    'id' => $s['id'] ?? null,
                    'name' => $s['name'] ?? null,
                    'approved_at' => null,
                    'status' => 'pending',
                ];
            }, $estimate->approval_flow);
            if (Schema::hasColumn('estimates', 'approval_started')) {
                $validated['approval_started'] = true;
            }
        }

        if ($status === 'draft' && Schema::hasColumn('estimates', 'approval_started')) {
            $validated['approval_started'] = false;
        }

        if ($estimate->status === 'draft' && $status !== 'draft') {
            $validated['estimate_number'] = Estimate::generateReadableEstimateNumber(
                $validated['staff_id'] ?? $estimate->staff_id,
                $validated['client_id'] ?? $estimate->client_id,
                false
            );
        }

        $estimate->update(array_merge($validated, ['status' => $status]));
        $estimate->refresh();

        if ($originalStatus !== 'pending' && $status === 'pending') {
            $this->notifyApprovalRequested($estimate, Auth::user());
        }

        return redirect()->route('estimates.edit', $estimate->id)
            ->with('success', 'Quote updated successfully.');
    }

    public function cancel(Estimate $estimate)
    {
        $estimate->status = 'draft';
        $estimate->approval_started = false;
        $estimate->approval_flow = [];
        $estimate->save();

        return redirect()->route('estimates.edit', $estimate->id)->with('success', '承認申請を取り消しました。');
    }

    public function destroy(Estimate $estimate)
    {
        $estimate->delete();
        return redirect()->route('quotes.index')->with('success', '見積書を削除しました。');
    }

    public function previewPdf(Request $request)
    {
        $estimateData = $request->all();
        return view('estimates.pdf', compact('estimateData'));
    }

    public function updateApproval(Estimate $estimate)
    {
        $user = Auth::user();

        $flow = $estimate->approval_flow;
        if (!is_array($flow) || empty($flow)) {
            return redirect()->back()->withErrors(['approval' => '承認フローが設定されていません。']);
        }

        $currentIndex = -1;
        foreach ($flow as $idx => $step) {
            if (empty($step['approved_at'])) {
                $currentIndex = $idx;
                break;
            }
        }

        if ($currentIndex === -1) {
            if ($estimate->status !== 'sent') {
                $estimate->status = 'sent';
                $estimate->save();
            }
            return redirect()->back()->with('success', '既に承認済みです。');
        }

        $currentStep = $flow[$currentIndex];
        $currId = $currentStep['id'] ?? null;
        $matchesLocalId = is_numeric($currId) && (int)$currId === (int)$user->id;
        $currIdStr = is_null($currId) ? '' : (string)$currId;
        $userExt = (string)($user->external_user_id ?? '');
        $matchesExternalId = ($currIdStr !== '') && ($userExt !== '') && ($currIdStr === $userExt);
        if (!($matchesLocalId || $matchesExternalId)) {
            return redirect()->back()->withErrors(['approval' => '現在の承認ステップの担当者ではありません。']);
        }

        $flow[$currentIndex]['approved_at'] = now()->toDateTimeString();
        $flow[$currentIndex]['status'] = 'approved';
        $updatedStep = $flow[$currentIndex];
        $estimate->approval_flow = $flow;

        $allApproved = true;
        foreach ($flow as $step) {
            if (empty($step['approved_at'])) {
                $allApproved = false;
                break;
            }
        }
        if ($allApproved) {
            $estimate->status = 'sent';
            $estimate->approval_started = false;
        }

        $estimate->save();

        $this->notifyApprovalStepCompleted($estimate, $updatedStep, Auth::user());

        if ($allApproved) {
            $this->notifyApprovalCompleted($estimate);
        }

        return redirect()->back()->with('success', '承認しました。');
    }

    public function duplicate(Estimate $estimate)
    {
        $newEstimate = $estimate->replicate();
        $newEstimate->estimate_number = Estimate::generateReadableEstimateNumber(
            $estimate->staff_id ?? null,
            $estimate->client_id ?? null,
            true
        );
        $newEstimate->status = 'draft';
        $newEstimate->save();

        return redirect()->route('estimates.edit', $newEstimate->id)
            ->with('success', '見積書を複製しました。');
    }

    public function generateNotes(Request $request)
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
            'estimate_id' => ['nullable', 'integer', 'exists:estimates,id'],
        ]);

        $apiKey = (string) config('services.openai.key', '');
        if ($apiKey === '') {
            return response()->json([
                'message' => 'OpenAI APIキーが未設定のため備考を生成できません。',
            ], 422);
        }

        $baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com'), '/');
        $model = (string) config('services.openai.model', 'gpt-4o-mini');

        $contextPieces = [];
        if (!empty($validated['estimate_id'])) {
            $estimate = Estimate::find($validated['estimate_id']);
            if ($estimate) {
                if (!empty($estimate->customer_name)) {
                    $contextPieces[] = '顧客: ' . $estimate->customer_name;
                }
                if (!empty($estimate->title)) {
                    $contextPieces[] = '案件タイトル: ' . $estimate->title;
                }
                if (!empty($estimate->issue_date)) {
                    $issueDate = $estimate->issue_date instanceof Carbon
                        ? $estimate->issue_date->format('Y-m-d')
                        : (string) $estimate->issue_date;
                    $contextPieces[] = '見積日: ' . $issueDate;
                }
                if (is_array($estimate->items) && count($estimate->items) > 0) {
                    $summaries = collect($estimate->items)
                        ->take(5)
                        ->map(function ($item) {
                            $name = $item['name'] ?? ($item['description'] ?? '項目');
                            $qty = $item['qty'] ?? $item['quantity'] ?? null;
                            $unit = $item['unit'] ?? '';
                            $price = $item['price'] ?? null;
                            $parts = array_filter([
                                $name,
                                $qty ? $qty . ($unit ? $unit : '') : null,
                                $price ? '単価' . number_format((float) $price) . '円' : null,
                            ]);
                            return implode(' / ', $parts);
                        })
                        ->filter()
                        ->all();
                    if (!empty($summaries)) {
                        $contextPieces[] = '主要項目: ' . implode(', ', $summaries);
                    }
                }
            }
        }

        $userPrompt = trim($validated['prompt']);
        $userContent = $userPrompt;
        if (!empty($contextPieces)) {
            $userContent = "参考情報:\n" . implode("\n", $contextPieces) . "\n\n備考に反映したい要望:\n" . $userPrompt;
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'You compose professional Japanese estimate remarks. Structure the response into multiple sections, each beginning with full-width bracket labels such as 【検収基準】, 【納期】, 【前提条件】, 【変更管理】, 【保守保証】, choosing only relevant titles. Each section should contain 1〜2 sentences in polite Japanese (です・ます調) with no extra quotation marks around the label. Insert a single blank line between sections for readability. Only include sections that match the provided information, and do not use bullet points or hyphen prefixes. If neither the user prompt nor the context contains the word リスク, avoid using that word in the response.',
            ],
            [
                'role' => 'user',
                'content' => $userContent,
            ],
        ];

        $streamHandler = HandlerStack::create(new StreamHandler());

        try {
            $response = Http::withOptions([
                'handler' => $streamHandler,
            ])->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(20)->post($baseUrl . '/v1/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.3,
                'max_tokens' => 300,
            ]);
        } catch (\Throwable $e) {
            Log::error('OpenAI 備考生成の呼び出しに失敗しました。', [
                'exception' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => '備考の生成に失敗しました。時間を置いて再度お試しください。',
            ], 500);
        }

        if (!$response->successful()) {
            Log::warning('OpenAI 備考生成の応答が失敗しました。', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return response()->json([
                'message' => '備考の生成に失敗しました。',
            ], 500);
        }

        $notes = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        if ($notes !== '') {
            $lines = array_filter(array_map('rtrim', preg_split("/\r?\n/", $notes)), static fn($line) => $line !== '');
            $notes = implode("\n", $lines);
        }
        if ($notes === '') {
            return response()->json([
                'message' => '有効な文面を生成できませんでした。',
            ], 500);
        }

        return response()->json([
            'notes' => $notes,
        ]);
    }

    public function bulkApprove(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:estimates,id',
        ]);

        Estimate::whereIn('id', $request->ids)->update(['status' => 'sent']);

        return redirect()->back()->with('success', count($request->ids) . '件の見積書を承認申請しました。');
    }

    public function bulkReassign(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:estimates,id',
        ]);

        return redirect()->back()->with('success', count($request->ids) . '件の見積書の担当者付替を処理しました。');
    }

    // --- Money Forward Integration (New Refresh Token Flow) ---

    public function createMfQuote(Estimate $estimate, Request $request, MoneyForwardApiService $apiService)
    {
        if ($token = $apiService->getValidAccessToken(null, 'mfc/invoice/data.write')) {
            return $this->_doCreateMfQuote($estimate, $token, $apiService);
        } else {
            $request->session()->put('mf_redirect_action', 'create_quote');
            $request->session()->put('mf_estimate_id', $estimate->id);
            return redirect()->route('estimates.auth.start');
        }
    }

    public function viewMfQuotePdf(Estimate $estimate, Request $request, MoneyForwardApiService $apiService)
    {
        if (empty($estimate->mf_quote_id)) {
            return redirect()->back()->with('error', 'Money Forward quote has not been created yet.');
        }
        if ($token = $apiService->getValidAccessToken(null, 'mfc/invoice/data.read')) {
            return $this->_doViewMfQuotePdf($estimate, $token, $apiService);
        } else {
            $request->session()->put('mf_redirect_action', 'view_quote_pdf');
            $request->session()->put('mf_estimate_id', $estimate->id);
            return redirect()->route('estimates.auth.start');
        }
    }

    public function convertMfQuoteToBilling(Estimate $estimate, Request $request, MoneyForwardApiService $apiService)
    {
        if (empty($estimate->mf_quote_id)) {
            return redirect()->back()->with('error', 'Money Forward quote has not been created yet.');
        }
        if ($token = $apiService->getValidAccessToken(null, 'mfc/invoice/data.write')) {
            return $this->_doConvertMfQuoteToBilling($estimate, $token, $apiService);
        } else {
            $request->session()->put('mf_redirect_action', 'convert_to_billing');
            $request->session()->put('mf_estimate_id', $estimate->id);
            return redirect()->route('estimates.auth.start');
        }
    }

    public function redirectToAuth(Request $request)
    {
        $action = $request->session()->get('mf_redirect_action');
        $scope = in_array($action, ['create_quote', 'convert_to_billing'])
            ? 'mfc/invoice/data.write'
            : 'mfc/invoice/data.read';

        $redirectUri = $this->resolveRedirectUriForAction($action);

        $authUrl = config('services.money_forward.authorization_url') . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.money_forward.client_id'),
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
        ]);
        return Inertia::location($authUrl);
    }

    public function handleCallback(Request $request, MoneyForwardApiService $apiService)
    {
        if (!$request->has('code')) {
            return redirect()->route('quotes.index')->with('error', 'Authorization failed.');
        }

        $action = $request->session()->get('mf_redirect_action');
        $redirectUri = $this->resolveRedirectUriForAction($action);

        $tokenData = $apiService->getAccessTokenFromCode($request->code, $redirectUri);
        if (!$tokenData) {
            return redirect()->route('quotes.index')->with('error', 'Failed to get access token.');
        }

        $apiService->storeToken($tokenData, Auth::id());
        $token = $tokenData['access_token'];

        $estimateId = $request->session()->get('mf_estimate_id');
        $estimate = Estimate::find($estimateId);

        if (!$estimate) {
            return redirect()->route('quotes.index')->with('error', 'Estimate not found in session.');
        }

        switch ($action) {
            case 'create_quote':
                return $this->_doCreateMfQuote($estimate, $token, $apiService);
            case 'view_quote_pdf':
                return $this->_doViewMfQuotePdf($estimate, $token, $apiService);
            case 'convert_to_billing':
                return $this->_doConvertMfQuoteToBilling($estimate, $token, $apiService);
            default:
                return redirect()->route('quotes.index')->with('error', 'Unknown action for callback.');
        }
    }

    private function _doCreateMfQuote(Estimate $estimate, string $token, MoneyForwardApiService $apiService)
    {
        $result = $apiService->createQuoteFromEstimate($estimate, $token);

        if ($result && isset($result['id'])) {
            $estimate->mf_quote_id = $result['id'];
            if (isset($result['pdf_url'])) {
                $estimate->mf_quote_pdf_url = $result['pdf_url'];
            }
            $estimate->save();
            return redirect()->route('estimates.edit', $estimate->id)->with('success', 'Successfully created quote in Money Forward.');
        } else {
            Log::error('MF create quote failed', ['response' => $result]);
            $msg = 'Failed to create quote in Money Forward.';
            if (is_array($result) && isset($result['error_message'])) {
                $msg .= ' ' . $result['error_message'];
            }
            return redirect()->route('estimates.edit', $estimate->id)->with('error', $msg);
        }
    }

    private function _doViewMfQuotePdf(Estimate $estimate, string $token, MoneyForwardApiService $apiService)
    {
        try {
            $client = new \GuzzleHttp\Client();
            $pdfResp = $client->get(config('services.money_forward.api_url') . "/quotes/{$estimate->mf_quote_id}.pdf", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/pdf',
                ],
            ]);

            $tempRelativePath = 'temp/mf_quote_' . $estimate->id . '.pdf';
            Storage::put($tempRelativePath, $pdfResp->getBody()->getContents());

            $tempAbsolutePath = Storage::path($tempRelativePath);

            $headers = [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="quote.pdf"',
            ];

            return response()->download($tempAbsolutePath, 'quote.pdf', $headers)->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            Log::error('Failed to fetch MF quote PDF: ' . $e->getMessage(), ['estimate_id' => $estimate->id]);
            return redirect()->route('estimates.edit', $estimate->id)->with('error', 'Failed to get quote PDF.');
        }
    }

    private function _doConvertMfQuoteToBilling(Estimate $estimate, string $token, MoneyForwardApiService $apiService)
    {
        $result = $apiService->convertQuoteToBilling($estimate->mf_quote_id, $token);

        if (is_array($result) && isset($result['id'])) {
            $estimate->mf_invoice_id = $result['id'];
            if (isset($result['pdf_url']) && Schema::hasColumn('estimates', 'mf_invoice_pdf_url')) {
                $estimate->mf_invoice_pdf_url = $result['pdf_url'];
            }
            $estimate->save();
            return redirect()->route('estimates.edit', $estimate->id)->with('success', 'Successfully converted to invoice.');
        } else {
            Log::error('MF convert_to_billing failed', ['response' => $result]);
            $msg = 'Failed to convert to invoice.';
            if (is_array($result) && isset($result['message'])) { $msg .= ' ' . $result['message']; }
            return redirect()->route('estimates.edit', $estimate->id)->with('error', $msg);
        }
    }

    private function notifyApprovalRequested(Estimate $estimate, ?User $initiator = null): void
    {
        $webhook = (string) config('services.google_chat.approval_webhook', '');
        if ($webhook === '') {
            return;
        }

        $pendingNames = $this->collectPendingApproverNames($estimate->approval_flow);
        $initiatorName = $initiator?->name ?? 'システム';
        $pendingText = empty($pendingNames) ? '承認者未設定' : implode(', ', $pendingNames);

        $message = sprintf(
            "承認申請: %s\n件名: %s\n顧客: %s\n申請者: %s\n承認者: %s\nURL: %s",
            $estimate->estimate_number,
            $estimate->title ?? '（件名未設定）',
            $estimate->customer_name ?? '（顧客未設定）',
            $initiatorName,
            $pendingText,
            route('estimates.edit', $estimate->id)
        );

        $this->sendChatNotification($webhook, $message);
    }

    private function notifyApprovalStepCompleted(Estimate $estimate, array $step, ?User $actor = null): void
    {
        $webhook = (string) config('services.google_chat.approval_webhook', '');
        if ($webhook === '') {
            return;
        }

        $approverName = $step['name'] ?? $actor?->name ?? '承認者';
        $pendingNames = $this->collectPendingApproverNames($estimate->approval_flow);
        $pendingText = empty($pendingNames) ? 'なし（残り承認者なし）' : implode(', ', $pendingNames);

        $message = sprintf(
            "承認完了: %s\n承認者: %s\n残り承認者: %s\nURL: %s",
            $estimate->estimate_number,
            $approverName,
            $pendingText,
            route('estimates.edit', $estimate->id)
        );

        $this->sendChatNotification($webhook, $message);
    }

    private function notifyApprovalCompleted(Estimate $estimate): void
    {
        $webhook = (string) config('services.google_chat.approval_webhook', '');
        if ($webhook === '') {
            return;
        }

        $message = sprintf(
            "最終承認完了: %s\n件名: %s\n顧客: %s\nURL: %s",
            $estimate->estimate_number,
            $estimate->title ?? '（件名未設定）',
            $estimate->customer_name ?? '（顧客未設定）',
            route('estimates.edit', $estimate->id)
        );

        $this->sendChatNotification($webhook, $message);
    }

    private function sendChatNotification(string $webhook, string $message): void
    {
        try {
            $response = Http::timeout(5)->post($webhook, ['text' => $message]);
            if (!$response->successful()) {
                Log::warning('Google Chat通知に失敗しました。', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Google Chat通知の送信に失敗しました。', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function collectPendingApproverNames($flow): array
    {
        if (!is_array($flow)) {
            return [];
        }

        $names = [];
        foreach ($flow as $step) {
            $approved = $step['approved_at'] ?? null;
            $status = $step['status'] ?? null;
            if (empty($approved) && ($status === 'pending' || $status === null)) {
                $name = $step['name'] ?? null;
                if ($name) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    private function resolveRedirectUriForAction(?string $action): string
    {
        return match ($action) {
            'create_quote' => env('MONEY_FORWARD_ESTIMATE_REDIRECT_URI', url('/estimates/create-quote/callback')),
            'convert_to_billing' => env('MONEY_FORWARD_CONVERT_REDIRECT_URI', url('/estimates/convert-to-billing/callback')),
            'view_quote_pdf' => env('MONEY_FORWARD_QUOTE_VIEW_REDIRECT_URI', url('/estimates/view-quote/callback')),
            default => env('MONEY_FORWARD_QUOTE_REDIRECT_URI', route('estimates.auth.callback')),
        };
    }
}
