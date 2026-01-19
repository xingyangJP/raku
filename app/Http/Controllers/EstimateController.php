<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use App\Models\EstimateAiLog;
use App\Models\Partner;
use App\Models\Product;
use App\Models\User;
use App\Services\MoneyForwardApiService;
use App\Services\MoneyForwardQuoteSynchronizer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Vite;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\StreamHandler;
use App\Models\MfToken;

class EstimateController extends Controller
{
    private function loadProducts()
    {
        if (Schema::hasTable('products')) {
            $columns = ['products.id', 'products.name', 'products.price', 'products.cost', 'products.unit', 'products.sku as code', 'products.description'];
            if (Schema::hasColumn('products', 'business_division')) {
                $columns[] = 'products.business_division';
            }
            if (Schema::hasTable('categories')) {
                $columns[] = 'categories.name as category_name';
                $columns[] = 'categories.code as category_code';
            }

            $query = DB::table('products')
                ->when(Schema::hasTable('categories'), function ($builder) {
                    $builder->leftJoin('categories', 'products.category_id', '=', 'categories.id');
                })
                ->where('is_active', true)
                ->orderBy('name')
                ->get($columns);

            return $query;
        }
        return [];
    }

    private function updatePartnerContactCache(?string $partnerId, ?string $departmentId, ?string $name, ?string $title, ?string $officeMemberName = null): void
    {
        if (empty($partnerId) || empty($departmentId)) {
            return;
        }

        try {
            $partner = Partner::where('mf_partner_id', $partnerId)->first();
            if (!$partner || !is_array($partner->payload)) {
                return;
            }

            $payload = $partner->payload;
            if ($this->mutateDepartmentContact($payload, $departmentId, [
                'person_name' => $this->restoreBlank($name),
                'person_title' => $this->restoreBlank($title),
                'office_member_name' => $this->restoreBlank($officeMemberName),
            ])) {
                $partner->payload = $payload;
                $partner->save();
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to update local partner payload with contact info.', [
                'partner_id' => $partnerId,
                'department_id' => $departmentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function mutateDepartmentContact(&$node, string $departmentId, array $updates): bool
    {
        if (!is_array($node)) {
            return false;
        }

        $changed = false;
        if (isset($node['id']) && (string) $node['id'] === (string) $departmentId) {
            foreach (['person_name', 'person_title', 'office_member_name'] as $field) {
                if (array_key_exists($field, $updates)) {
                    $value = $updates[$field];
                    if (is_string($value) && trim($value) === '') {
                        $value = '';
                    }
                    $current = $node[$field] ?? null;
                    if ($current !== $value) {
                        if ($value === null || $value === '') {
                            unset($node[$field]);
                        } else {
                            $node[$field] = $value;
                        }
                        $changed = true;
                    }
                }
            }
        }

        foreach ($node as &$value) {
            if (is_array($value) && $this->mutateDepartmentContact($value, $departmentId, $updates)) {
                $changed = true;
            }
        }

        return $changed;
    }

    private function extractDepartmentContact(?array $payload, string $departmentId): array
    {
        if (!is_array($payload)) {
            return ['person_name' => null, 'person_title' => null, 'office_member_name' => null];
        }

        $stack = [$payload];
        while ($stack) {
            $node = array_pop($stack);
            if (!is_array($node)) {
                continue;
            }
            if (isset($node['id']) && (string) $node['id'] === (string) $departmentId) {
                return [
                    'person_name' => $node['person_name'] ?? null,
                    'person_title' => $node['person_title'] ?? null,
                ];
            }
            foreach ($node as $value) {
                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }

        return ['person_name' => null, 'person_title' => null, 'office_member_name' => null];
    }

    private function normalizePartnerField($value, int $limit, string $placeholder = ' '): string
    {
        if (!is_string($placeholder) || $placeholder === '') {
            $placeholder = ' ';
        }

        $text = (string) $value;
        $text = mb_substr($text, 0, $limit);
        if (trim($text) === '') {
            return $placeholder;
        }
        return $text;
    }

    private function restoreBlank($value)
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || $value === '-' || $value === 'ご担当者様') {
                return '';
            }
        }
        return $value;
    }

    private function syncPartnerContactWithMoneyForward(Estimate $estimate, string $token, MoneyForwardApiService $apiService): void
    {
        $partnerId = $estimate->client_id;
        $departmentId = $estimate->mf_department_id;
        if (empty($partnerId) || empty($departmentId)) {
            return;
        }

        $payload = [
            'person_name' => $this->normalizePartnerField($estimate->client_contact_name, 35, 'ご担当者様'),
            'person_title' => $this->normalizePartnerField($estimate->client_contact_title, 35, '-'),
            'office_member_name' => $this->normalizePartnerField($estimate->staff_name, 40),
        ];

        $result = $apiService->updateDepartmentContact($partnerId, $departmentId, $payload, $token);
        if (is_array($result)) {
            $this->updatePartnerContactCache(
                $partnerId,
                $departmentId,
                $result['person_name'] ?? $payload['person_name'] ?? null,
                $result['person_title'] ?? $payload['person_title'] ?? null,
                $result['office_member_name'] ?? $payload['office_member_name'] ?? null
            );
        } elseif ($result === false) {
            Log::warning('Money Forward department contact update returned no data.', [
                'partner_id' => $partnerId,
                'department_id' => $departmentId,
            ]);
        } else {
            $this->updatePartnerContactCache(
                $partnerId,
                $departmentId,
                $payload['person_name'],
                $payload['person_title'],
                $payload['office_member_name']
            );
        }
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
        $focusEstimateId = $request->query('estimate_id', $request->query('quote_id'));
        $focusEstimateId = is_numeric($focusEstimateId) ? (int) $focusEstimateId : null;

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

        $partnerCodes = collect();
        $clientIds = $estimates->pluck('client_id')->filter()->unique();
        if ($clientIds->isNotEmpty() && Schema::hasTable('partners')) {
            $partnerCodes = Partner::whereIn('mf_partner_id', $clientIds)->pluck('code', 'mf_partner_id');
        }

        $estimates->each(function ($estimate) use ($partnerCodes) {
            $source = $partnerCodes[$estimate->client_id] ?? $estimate->client_id;
            $pmCusId = $this->extractPmCustomerId($source);
            if ($pmCusId !== null) {
                $estimate->setAttribute('pm_customer_id', $pmCusId);
            }
        });

        $products = $this->loadProducts();

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

        // フィルタに指定された月があればそれを優先して保守合計を計算（無ければ今月）
        $maintenanceTargetMonth = $fromMonth ?? $toMonth ?? $currentMonth->format('Y-m');
        $maintenanceMonthCarbon = Carbon::createFromFormat('Y-m', $maintenanceTargetMonth, $timezone) ?: $currentMonth;
        $maintenanceFee = $this->fetchMaintenanceTotal($maintenanceMonthCarbon);

        return Inertia::render('Quotes/Index', [
            'estimates' => $estimates,
            'products' => $products,
            'products' => $products,
            'syncStatus' => $syncStatus,
            'moneyForwardConfig' => $moneyForwardConfig,
            'error' => session('error'),
            'defaultRange' => $defaultRange,
            'initialFilters' => $initialFilters,
            'focusEstimateId' => $focusEstimateId,
            'customerPortalBase' => rtrim(config('services.customer_portal.base_url', 'https://pm.xerographix.co.jp/customers'), '/'),
            'maintenance_fee_total' => $maintenanceFee,
            'maintenance_month' => $maintenanceMonthCarbon->format('Y-m'),
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
            $rules = [
                'customer_name' => 'required|string|max:255',
                'client_contact_name' => 'nullable|string|max:35',
                'client_contact_title' => 'nullable|string|max:35',
                'client_id' => 'nullable|string|max:255',
                'mf_department_id' => 'nullable|string|max:255',
                'title' => 'required|string|max:255',
                'issue_date' => 'nullable|date',
                'due_date' => 'nullable|date',
                'delivery_date' => 'nullable|date',
                'total_amount' => 'required|integer',
                'tax_amount' => 'required|integer',
                'notes' => 'nullable|string',
                'internal_memo' => 'nullable|string',
                'google_docs_url' => 'nullable|url|max:2048',
                'delivery_location' => 'nullable|string',
                'items' => 'required|array|min:1',
                'estimate_number' => 'required|string|max:255|unique:estimates,estimate_number,' . $estimate->id,
                'staff_id' => 'nullable|integer',
                'staff_name' => 'required|string|max:255',
                'requirement_summary' => 'nullable|string|max:4000',
                'structured_requirements' => 'nullable|array',
            ];

            if (!Schema::hasColumn('estimates', 'client_contact_name')) {
                unset($rules['client_contact_name'], $rules['client_contact_title']);
            }

            $validated = $request->validate($rules);
            $validated['structured_requirements'] = $this->normalizeStructuredRequirements($validated['structured_requirements'] ?? null);
            $estimate->update(array_merge($validated, ['status' => 'draft']));
            $this->updatePartnerContactCache(
                $validated['client_id'] ?? $estimate->client_id,
                $validated['mf_department_id'] ?? $estimate->mf_department_id,
                $validated['client_contact_name'] ?? null,
                $validated['client_contact_title'] ?? null,
                $validated['staff_name'] ?? $estimate->staff_name ?? null
            );
            return redirect()->route('estimates.edit', $estimate->id)->with('success', 'Draft updated successfully.');
        } else {
            $validated = $request->validate([
                'customer_name' => 'required|string|max:255',
                'client_contact_name' => 'nullable|string|max:35',
                'client_contact_title' => 'nullable|string|max:35',
                'client_id' => 'nullable|string|max:255',
                'mf_department_id' => 'nullable|string|max:255',
                'title' => 'required|string|max:255',
                'issue_date' => 'nullable|date',
                'due_date' => 'nullable|date',
                'delivery_date' => 'nullable|date',
                'total_amount' => 'required|integer',
                'tax_amount' => 'required|integer',
                'notes' => 'nullable|string',
                'internal_memo' => 'nullable|string',
                'google_docs_url' => 'nullable|url|max:2048',
                'delivery_location' => 'nullable|string',
                'items' => 'required|array|min:1',
                'estimate_number' => 'nullable|string|max:255|unique:estimates,estimate_number',
                'staff_id' => 'nullable|integer',
                'staff_name' => 'required|string|max:255',
                'requirement_summary' => 'nullable|string|max:4000',
                'structured_requirements' => 'nullable|array',
            ]);

            $validated['structured_requirements'] = $this->normalizeStructuredRequirements($validated['structured_requirements'] ?? null);

            if (empty($validated['estimate_number'])) {
                $validated['estimate_number'] = Estimate::generateReadableEstimateNumber(
                    $validated['staff_id'] ?? null,
                    $validated['client_id'] ?? null,
                    true
                );
            }
            
            $estimate = Estimate::create(array_merge($validated, ['status' => 'draft']));
            $this->updatePartnerContactCache(
                $validated['client_id'] ?? null,
                $validated['mf_department_id'] ?? null,
                $validated['client_contact_name'] ?? null,
                $validated['client_contact_title'] ?? null,
                $validated['staff_name'] ?? null
            );
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

        $rules = [
            'customer_name' => 'required|string|max:255',
            'client_contact_name' => 'nullable|string|max:35',
            'client_contact_title' => 'nullable|string|max:35',
            'client_id' => 'nullable|string|max:255',
            'mf_department_id' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'total_amount' => 'required|integer',
            'tax_amount' => 'required|integer',
            'notes' => 'nullable|string',
            'internal_memo' => 'nullable|string',
            'google_docs_url' => 'nullable|url|max:2048',
            'delivery_location' => 'nullable|string',
            'items' => 'required|array',
            'estimate_number' => 'nullable|string|max:255|unique:estimates,estimate_number',
            'staff_id' => 'nullable|integer',
            'staff_name' => 'nullable|string|max:255',
            'approval_flow' => 'nullable|array',
            'status' => 'nullable|string|in:draft,pending,sent,rejected',
            'is_order_confirmed' => 'sometimes|boolean',
            'requirement_summary' => 'nullable|string|max:4000',
            'structured_requirements' => 'nullable|array',
        ];

        if (!Schema::hasColumn('estimates', 'client_contact_name')) {
            unset($rules['client_contact_name'], $rules['client_contact_title']);
        }

        $validated = $request->validate($rules);
        $validated['structured_requirements'] = $this->normalizeStructuredRequirements($validated['structured_requirements'] ?? null);

        if (!empty($validated['issue_date'])) {
            $validated['issue_date'] = date('Y-m-d', strtotime($validated['issue_date']));
        }
        if (!empty($validated['due_date'])) {
            $validated['due_date'] = date('Y-m-d', strtotime($validated['due_date']));
        }
        if (!empty($validated['delivery_date'])) {
            $validated['delivery_date'] = date('Y-m-d', strtotime($validated['delivery_date']));
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
        $requestedOrderConfirmed = array_key_exists('is_order_confirmed', $validated)
            ? (bool) $validated['is_order_confirmed']
            : false;
        $validated['is_order_confirmed'] = $status === 'sent' ? $requestedOrderConfirmed : false;
        if (isset($validated['approval_flow']) && is_array($validated['approval_flow'])) {
            $hasUnapproved = false;
            $normalizedFlow = [];
            foreach ($validated['approval_flow'] as $step) {
                $approvedAt = $status === 'pending' ? null : ($step['approved_at'] ?? null);
                $rejectedAt = $status === 'pending' ? null : ($step['rejected_at'] ?? null);
                $statusValue = $approvedAt
                    ? 'approved'
                    : ($rejectedAt ? 'rejected' : 'pending');
                if ($statusValue !== 'approved') { $hasUnapproved = true; }
                $normalizedFlow[] = [
                    'id' => $step['id'] ?? null,
                    'name' => $step['name'] ?? null,
                    'approved_at' => $approvedAt,
                    'rejected_at' => $statusValue === 'rejected' ? $rejectedAt : null,
                    'rejection_reason' => $statusValue === 'rejected' ? ($step['rejection_reason'] ?? null) : null,
                    'requirements_checked' => $step['requirements_checked'] ?? null,
                    'requirements_checked_at' => $step['requirements_checked_at'] ?? null,
                    'status' => $statusValue,
                ];
            }
            $validated['approval_flow'] = $normalizedFlow;
            if ($hasUnapproved && $status !== 'draft') { $status = 'pending'; }
            if (Schema::hasColumn('estimates', 'approval_started')) {
                $validated['approval_started'] = $hasUnapproved;
            }
        }

        $errors = [];
        $grossRateType5 = $this->calculateGrossMarginRate($validated['items'] ?? [], 'type5');
        $grossRateType1 = $this->calculateGrossMarginRate($validated['items'] ?? [], 'type1');
        $isLowMarginType5 = $grossRateType5 < 0.3;
        $isLowMarginType1 = $grossRateType1 < 0.05;
        if ($isLowMarginType5 || $isLowMarginType1) {
            $memo = trim((string) ($validated['internal_memo'] ?? ''));
            $lowMarginContext = [];
            if ($isLowMarginType5) { $lowMarginContext[] = '第5種粗利率30%未満'; }
            if ($isLowMarginType1) { $lowMarginContext[] = '第1種粗利率5%未満'; }
            $contextText = $lowMarginContext ? implode(' / ', $lowMarginContext) . 'の場合、' : '粗利率が低い場合、';
            if ($memo === '') {
                $errors['internal_memo'] = $contextText . '社内メモは必須です。';
            }
            if (!$this->approvalFlowIncludesRequiredApprover($validated['approval_flow'] ?? [])) {
                $errors['approval_flow'] = $contextText . '承認者に「守部幸洋」または「吉井靖人」を含めてください。';
            }
        }
        if ($this->requiresDesignOrDevelopmentAttachment($validated['items'] ?? [])) {
            $docsUrl = trim((string) ($validated['google_docs_url'] ?? ''));
            if ($docsUrl === '') {
                $errors['google_docs_url'] = '設計/開発の明細がある場合は要件定義書（必須）の入力が必要です。';
            }
        }
        if (!empty($errors)) {
            return back()->withErrors($errors)->withInput();
        }

        $estimate = Estimate::create(array_merge($validated, [
            'status' => $status,
            'is_order_confirmed' => $validated['is_order_confirmed'],
        ]));

        $this->updatePartnerContactCache(
            $validated['client_id'] ?? null,
            $validated['mf_department_id'] ?? null,
            $validated['client_contact_name'] ?? null,
            $validated['client_contact_title'] ?? null,
            $validated['staff_name'] ?? null
        );

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
            'client_contact_name' => 'nullable|string|max:35',
            'client_contact_title' => 'nullable|string|max:35',
            'client_id' => 'nullable|string|max:255',
            'mf_department_id' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'total_amount' => 'required|integer',
            'tax_amount' => 'required|integer',
            'notes' => 'nullable|string',
            'internal_memo' => 'nullable|string',
            'google_docs_url' => 'nullable|url|max:2048',
            'delivery_location' => 'nullable|string',
            'items' => 'required|array',
            'estimate_number' => 'required|string|max:255|unique:estimates,estimate_number,' . $estimate->id,
            'staff_id' => 'nullable|integer',
            'staff_name' => 'nullable|string|max:255',
            'approval_flow' => 'nullable|array',
            'status' => 'nullable|string|in:draft,pending,sent,rejected',
            'is_order_confirmed' => 'sometimes|boolean',
            'requirement_summary' => 'nullable|string|max:4000',
            'structured_requirements' => 'nullable|array',
        ]);

        $validated['structured_requirements'] = $this->normalizeStructuredRequirements($validated['structured_requirements'] ?? null);

        if (!empty($validated['issue_date'])) {
            $validated['issue_date'] = date('Y-m-d', strtotime($validated['issue_date']));
        }
        if (!empty($validated['due_date'])) {
            $validated['due_date'] = date('Y-m-d', strtotime($validated['due_date']));
        }
        if (!empty($validated['delivery_date'])) {
            $validated['delivery_date'] = date('Y-m-d', strtotime($validated['delivery_date']));
        }

        $originalStatus = $estimate->status;
        $originalClientId = $estimate->client_id;
        $status = $validated['status'] ?? $estimate->status;
        unset($validated['status']);
        $requestedOrderConfirmed = array_key_exists('is_order_confirmed', $validated)
            ? (bool) $validated['is_order_confirmed']
            : $estimate->is_order_confirmed;
        unset($validated['is_order_confirmed']);

        $clientChanged = array_key_exists('client_id', $validated)
            && (string) ($validated['client_id'] ?? '') !== (string) ($originalClientId ?? '');

        if (!Schema::hasColumn('estimates', 'approval_flow')) {
            unset($validated['approval_flow']);
        }

        if ($clientChanged && !empty($estimate->mf_quote_id)) {
            if ($status === 'sent') {
                $status = 'pending';
            }
            if ($status !== 'draft' && Schema::hasColumn('estimates', 'approval_started')) {
                $validated['approval_started'] = true;
            }
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
                    'requirements_checked' => $step['requirements_checked'] ?? null,
                    'requirements_checked_at' => $step['requirements_checked_at'] ?? null,
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
                    'rejected_at' => null,
                    'rejection_reason' => null,
                    'requirements_checked' => $s['requirements_checked'] ?? null,
                    'requirements_checked_at' => $s['requirements_checked_at'] ?? null,
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

        $validated['is_order_confirmed'] = $status === 'sent' ? $requestedOrderConfirmed : false;

        $errors = [];
        $grossRateType5 = $this->calculateGrossMarginRate($validated['items'] ?? [], 'type5');
        $grossRateType1 = $this->calculateGrossMarginRate($validated['items'] ?? [], 'type1');
        $isLowMarginType5 = $grossRateType5 < 0.3;
        $isLowMarginType1 = $grossRateType1 < 0.05;
        if ($isLowMarginType5 || $isLowMarginType1) {
            $memo = trim((string) ($validated['internal_memo'] ?? ''));
            $lowMarginContext = [];
            if ($isLowMarginType5) { $lowMarginContext[] = '第5種粗利率30%未満'; }
            if ($isLowMarginType1) { $lowMarginContext[] = '第1種粗利率5%未満'; }
            $contextText = $lowMarginContext ? implode(' / ', $lowMarginContext) . 'の場合、' : '粗利率が低い場合、';
            if ($memo === '') {
                $errors['internal_memo'] = $contextText . '社内メモは必須です。';
            }
            if (!$this->approvalFlowIncludesRequiredApprover($validated['approval_flow'] ?? [])) {
                $errors['approval_flow'] = $contextText . '承認者に「守部幸洋」または「吉井靖人」を含めてください。';
            }
        }
        if ($this->requiresDesignOrDevelopmentAttachment($validated['items'] ?? [])) {
            $docsUrl = trim((string) ($validated['google_docs_url'] ?? ''));
            if ($docsUrl === '') {
                $errors['google_docs_url'] = '設計/開発の明細がある場合は要件定義書（必須）の入力が必要です。';
            }
        }
        if (!empty($errors)) {
            return back()->withErrors($errors)->withInput();
        }

        $estimate->update(array_merge($validated, ['status' => $status]));
        $this->updatePartnerContactCache(
            $estimate->client_id,
            $estimate->mf_department_id,
            $validated['client_contact_name'] ?? $estimate->client_contact_name,
            $validated['client_contact_title'] ?? $estimate->client_contact_title,
            $validated['staff_name'] ?? $estimate->staff_name
        );
        $wasMfQuotePresent = !empty($estimate->mf_quote_id);
        $estimate->refresh();

        if ($wasMfQuotePresent && (($originalStatus === 'sent' && $status !== 'sent') || $clientChanged)) {
            $this->deleteMoneyForwardQuote($estimate->mf_quote_id);
            $this->clearMoneyForwardQuoteState($estimate, false);
            $estimate->save();
        }

        if ($originalStatus !== 'pending' && $status === 'pending') {
            $this->notifyApprovalRequested($estimate, Auth::user());
        }

        return redirect()->route('estimates.edit', $estimate->id)
            ->with('success', 'Quote updated successfully.');
    }

    public function cancel(Estimate $estimate)
    {
        if (!empty($estimate->mf_quote_id)) {
            $this->deleteMoneyForwardQuote($estimate->mf_quote_id);
            $this->clearMoneyForwardQuoteState($estimate, false);
        }

        $estimate->status = 'draft';
        $estimate->approval_started = false;
        $estimate->approval_flow = [];
        $estimate->is_order_confirmed = false;
        $estimate->save();

        $this->notifyApprovalCancelled($estimate, Auth::user());

        return redirect()->route('estimates.edit', $estimate->id)->with('success', '承認申請を取り消しました。');
    }

    public function destroy(Estimate $estimate)
    {
        if (!empty($estimate->mf_quote_id)) {
            $this->deleteMoneyForwardQuote($estimate->mf_quote_id);
        }

        $estimate->delete();
        return redirect()->route('quotes.index')->with('success', '見積書を削除しました。');
    }

    public function updateOrderConfirmation(Request $request, Estimate $estimate)
    {
        if ($estimate->status !== 'sent') {
            return redirect()->back()->withErrors(['order' => '承認済みの見積のみ受注確定できます。']);
        }

        $confirmed = $request->boolean('confirmed');
        $estimate->is_order_confirmed = $confirmed;
        $estimate->save();

        if ($confirmed) {
            $this->notifyOrderConfirmed($estimate, Auth::user());
            return redirect()->back()->with('success', '受注を確定しました。');
        }

        return redirect()->back()->with('success', '受注確定を解除しました。');
    }

    public function previewPdf(Request $request)
    {
        $estimateData = $request->all();
        return view('estimates.pdf', compact('estimateData'));
    }

    public function updateApproval(Request $request, Estimate $estimate)
    {
        $user = Auth::user();
        $action = $request->input('action', 'approve');

        $flow = $estimate->approval_flow;
        if (!is_array($flow) || empty($flow)) {
            return redirect()->back()->withErrors(['approval' => '承認フローが設定されていません。']);
        }

        $currentIndex = -1;
        foreach ($flow as $idx => $step) {
            $status = $step['status'] ?? (empty($step['approved_at']) ? 'pending' : 'approved');
            if ($status !== 'approved' && $status !== 'rejected') {
                $currentIndex = $idx;
                break;
            }
        }

        if ($currentIndex === -1) {
            if ($estimate->status === 'rejected') {
                return redirect()->back()->withErrors(['approval' => 'この見積書は却下されています。']);
            }
            if ($estimate->status !== 'sent') {
                $estimate->status = 'sent';
                $estimate->save();
            }
            return redirect()->back()->with('success', '既に承認済みです。');
        }

        $currentStep = $flow[$currentIndex];
        $currId = $currentStep['id'] ?? null;
        $currIdStr = is_null($currId) ? '' : (string)$currId;
        $userExt = (string)($user->external_user_id ?? '');

        $isAssigned = false;
        if ($userExt !== '') {
            if ($currIdStr !== '' && $currIdStr === $userExt) {
                $isAssigned = true;
            }
        } else {
            if (is_numeric($currId) && (int)$currId === (int)$user->id) {
                $isAssigned = true;
            }
        }

        if (!$isAssigned) {
            return redirect()->back()->withErrors(['approval' => '現在の承認ステップの担当者ではありません。']);
        }

        if ($action === 'reject') {
            $reason = trim((string) $request->input('reason', ''));
            if ($reason === '') {
                return redirect()->back()->withErrors(['approval' => '却下理由を入力してください。']);
            }

            $flow[$currentIndex]['approved_at'] = null;
            $flow[$currentIndex]['rejected_at'] = now()->toDateTimeString();
            $flow[$currentIndex]['status'] = 'rejected';
            $flow[$currentIndex]['rejection_reason'] = $reason;
            $estimate->approval_flow = $flow;
            $estimate->status = 'rejected';
            $estimate->approval_started = false;
            $estimate->save();

            $this->notifyApprovalRejected($estimate, $flow[$currentIndex], $user, $reason);

            return redirect()->back()->with('success', '却下しました。');
        }

        $flow[$currentIndex]['approved_at'] = now()->toDateTimeString();
        $flow[$currentIndex]['status'] = 'approved';
        $flow[$currentIndex]['rejected_at'] = null;
        $flow[$currentIndex]['rejection_reason'] = null;
        $updatedStep = $flow[$currentIndex];
        $estimate->approval_flow = $flow;

        $allApproved = true;
        foreach ($flow as $step) {
            $status = $step['status'] ?? (empty($step['approved_at']) ? 'pending' : 'approved');
            if ($status !== 'approved') {
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

    public function updateRequirementCheck(Request $request, Estimate $estimate)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'approver_id' => 'required',
            'checked' => 'required|boolean',
        ]);

        $flow = $estimate->approval_flow;
        if (!is_array($flow) || empty($flow)) {
            return response()->json(['message' => '承認フローが設定されていません。'], 422);
        }

        $approverId = (string) $validated['approver_id'];
        $userExt = (string) ($user->external_user_id ?? '');
        $userId = (string) ($user->id ?? '');
        $isSelf = ($approverId !== '' && ($approverId === $userExt || $approverId === $userId));
        if (!$isSelf) {
            return response()->json(['message' => 'この操作を行う権限がありません。'], 403);
        }

        $updated = false;
        foreach ($flow as $idx => $step) {
            $stepId = $step['id'] ?? null;
            $stepIdStr = is_null($stepId) ? '' : (string) $stepId;
            if ($stepIdStr !== $approverId) {
                continue;
            }
            $flow[$idx]['requirements_checked'] = (bool) $validated['checked'];
            $flow[$idx]['requirements_checked_at'] = $validated['checked'] ? now()->toDateTimeString() : null;
            $updated = true;
            break;
        }

        if (!$updated) {
            return response()->json(['message' => '承認者が見つかりません。'], 422);
        }

        $estimate->approval_flow = $flow;
        $estimate->save();

        return response()->json(['status' => 'ok']);
    }

    private function calculateGrossMarginRate($items, string $category = 'all'): float
    {
        $items = is_array($items) ? $items : [];
        $revenue = 0.0;
        $cost = 0.0;
        $matched = false;
        foreach ($items as $item) {
            if ($category === 'type5' && !$this->isTypeFiveItem($item)) { continue; }
            if ($category === 'type1' && !$this->isTypeOneItem($item)) { continue; }
            $matched = true;
            $qty = (float) (data_get($item, 'qty') ?? data_get($item, 'quantity', 1));
            if ($qty === 0.0) { $qty = 1.0; }
            $price = (float) (data_get($item, 'price') ?? data_get($item, 'unit_price', 0));
            $unitCost = (float) (data_get($item, 'cost') ?? data_get($item, 'unit_cost', 0));
            $revenue += $price * $qty;
            $cost += $unitCost * $qty;
        }
        if (!$matched) {
            return 1.0; // 対象品目が無い場合は判定対象外とみなす
        }
        if ($revenue <= 0) { return 0.0; }
        return ($revenue - $cost) / $revenue;
    }

    private function requiresDesignOrDevelopmentAttachment(array $items): bool
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

    private function isTypeFiveItem($item): bool
    {
        $division = (string) (data_get($item, 'business_division') ?? '');
        if ($division === 'fifth_business') {
            return true;
        }
        $name = (string) (data_get($item, 'name') ?? '');
        $code = (string) (data_get($item, 'code') ?? data_get($item, 'product_code') ?? '');
        $desc = (string) (data_get($item, 'description') ?? '');
        $text = mb_strtolower($name . ' ' . $code . ' ' . $desc);
        if (preg_match('/第\s*[5５]\s*種/u', $text)) {
            return true;
        }
        if (preg_match('/\b5\s*種/u', $text)) {
            return true;
        }
        return false;
    }

    private function isTypeOneItem($item): bool
    {
        $division = (string) (data_get($item, 'business_division') ?? '');
        if ($division === 'first_business') {
            return true;
        }
        $name = (string) (data_get($item, 'name') ?? '');
        $code = (string) (data_get($item, 'code') ?? data_get($item, 'product_code') ?? '');
        $desc = (string) (data_get($item, 'description') ?? '');
        $text = mb_strtolower($name . ' ' . $code . ' ' . $desc);
        if (preg_match('/第\s*[1１]\s*種/u', $text)) {
            return true;
        }
        if (preg_match('/\b1\s*種/u', $text)) {
            return true;
        }
        return false;
    }

    private function approvalFlowIncludesRequiredApprover($flow): bool
    {
        if (!is_array($flow)) { return false; }
        $required = ['守部幸洋', '吉井靖人'];
        foreach ($flow as $step) {
            $name = trim((string) ($step['name'] ?? ''));
            if (in_array($name, $required, true)) {
                return true;
            }
        }
        return false;
    }

    private function http(): PendingRequest
    {
        static $handlerStack = null;
        if ($handlerStack === null) {
            $handlerStack = HandlerStack::create(new StreamHandler());
        }

        return Http::withOptions([
            'handler' => $handlerStack,
        ]);
    }

    private function resolveOpenAiConfig(): array
    {
        $apiKey = (string) config('services.openai.key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('OpenAI APIキーが未設定のためAI機能を利用できません。');
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
        $trimmed = trim($trimmed);

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

    private function normalizePersonDays(float $days): ?float
    {
        if ($days <= 0) {
            return null;
        }
        $rounded = round($days * 2.0) / 2.0;
        if ($rounded < 0.5) {
            $rounded = 0.5;
        }
        return $rounded;
    }

    private function toFloat($value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value)) {
            $normalized = str_replace([',', '人', '日', 'h', 'H'], '', $value);
            return is_numeric($normalized) ? (float) $normalized : null;
        }
        return null;
    }

    private function normalizeStructuredRequirements($value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $functionalSource = $value['functional']
            ?? $value['functional_requirements']
            ?? $value['functionalRequirements']
            ?? [];
        $nonFunctionalSource = $value['non_functional']
            ?? $value['nonFunctional']
            ?? $value['non_functional_requirements']
            ?? $value['nonFunctionalRequirements']
            ?? [];
        $unresolvedSource = $value['unresolved']
            ?? $value['unresolved_requirements']
            ?? $value['pending']
            ?? $value['pending_requirements']
            ?? [];

        $functional = $this->normalizeRequirementLines($functionalSource);
        $nonFunctional = $this->normalizeRequirementLines($nonFunctionalSource);
        $unresolved = $this->normalizeRequirementLines($unresolvedSource);

        if (empty($functional) && empty($nonFunctional) && empty($unresolved)) {
            return null;
        }

        return [
            'functional' => $functional,
            'non_functional' => $nonFunctional,
            'unresolved' => $unresolved,
        ];
    }

    private function normalizeRequirementLines($lines): array
    {
        if (!is_array($lines)) {
            return [];
        }

        $normalized = [];
        foreach ($lines as $line) {
            if (is_array($line) || is_object($line)) {
                $line = json_encode($line, JSON_UNESCAPED_UNICODE);
            }
            $line = trim((string) $line);
            if ($line !== '') {
                $normalized[] = $line;
            }
        }

        return $normalized;
    }

    private function resolveFeatureDescription(?string $summary, ?string $productName, ?string $fallbackDescription, ?string $extraDetail = null): string
    {
        $text = trim((string) $summary);
        if ($text !== '' && $productName) {
            $pattern = '/^' . preg_quote($productName, '/') . '\s*(?:[|｜:：-]\s*)?/u';
            $stripped = preg_replace($pattern, '', $text, 1);
            if (is_string($stripped) && $stripped !== $text) {
                $text = trim($stripped);
            }
        }

        $extra = trim((string) $extraDetail);
        if ($extra !== '') {
            $text = $text !== '' ? "{$text}｜{$extra}" : $extra;
        }

        if ($text === '') {
            $text = trim((string) $fallbackDescription);
        }

        return $this->truncateDescription($text);
    }

    private function truncateDescription(string $text, int $limit = 40): string
    {
        if ($text === '') {
            return $text;
        }

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $trimmed = mb_substr($text, 0, $limit - 1);
        return rtrim($trimmed) . '…';
    }

    private function logAiEvent(?int $estimateId, string $action, array $structured, array $messages, ?string $aiResponse): void
    {
        if (!Schema::hasTable('estimate_ai_logs')) {
            return;
        }
        try {
            EstimateAiLog::create([
                'estimate_id' => $estimateId,
                'action' => $action,
                'input_summary' => $structured['summary'] ?? null,
                'structured_requirements' => $structured,
                'prompt_payload' => json_encode($messages, JSON_UNESCAPED_UNICODE),
                'ai_response' => $aiResponse,
            ]);
        } catch (\Throwable $e) {
            Log::warning('AIログの保存に失敗', ['error' => $e->getMessage()]);
        }
    }

    public function duplicate(Estimate $estimate)
    {
        $newEstimate = $estimate->replicate([
            'estimate_number',
            'status',
            'approval_flow',
            'approval_started',
            'mf_quote_id',
            'mf_quote_pdf_url',
            'mf_invoice_id',
            'mf_invoice_pdf_url',
            'mf_deleted_at',
        ]);

        $newEstimate->estimate_number = Estimate::generateReadableEstimateNumber(
            $estimate->staff_id ?? null,
            $estimate->client_id ?? null,
            $estimate->status === 'draft'
        );
        $newEstimate->status = 'draft';
        $newEstimate->approval_flow = [];
        if (Schema::hasColumn('estimates', 'approval_started')) {
            $newEstimate->approval_started = false;
        }

        $newEstimate->mf_quote_id = null;
        $newEstimate->mf_quote_pdf_url = null;
        $newEstimate->mf_invoice_id = null;
        $newEstimate->mf_invoice_pdf_url = null;
        $newEstimate->mf_deleted_at = null;

        $newEstimate->save();

        return redirect()->route('estimates.edit', $newEstimate->id)
            ->with('success', '見積書を複製しました。');
    }

    public function purchaseOrderPreview(Estimate $estimate)
    {
        abort_if($estimate->status !== 'sent', 403, '承認済み見積のみ注文書を表示できます。');

        $company = $this->buildCompanyProfile();
        $company['logoUrl'] = $this->resolveCompanyLogoUrl();

        return Inertia::render('Estimates/PurchaseOrderPreview', [
            'estimate' => $estimate,
            'company' => $company,
            'client' => $this->buildClientProfile($estimate),
            'purchaseOrderNumber' => $this->generatePurchaseOrderNumberForPreview($estimate),
        ]);
    }

    public function structureRequirementSummary(Request $request)
    {
        $validated = $request->validate([
            'requirement_summary' => 'required|string|max:4000',
            'estimate_id' => 'nullable|integer|exists:estimates,id',
        ]);

        try {
            $config = $this->resolveOpenAiConfig();
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a bilingual business analyst. Rewrite Japanese requirement notes into concise bullet lists. '
                    . 'Always output JSON with the keys "functional_requirements", "non_functional_requirements", and "unresolved_requirements". '
                    . 'Each value must be an array of 1〜6 short Japanese sentences (max 120 characters each). '
                    . 'Non-functional requirements must include performance, security, and operations if they are implied. '
                    . 'Use unresolved_requirements for 項目 that remain open, need確認, or depend on未定の前提条件.',
            ],
            [
                'role' => 'user',
                'content' => $validated['requirement_summary'],
            ],
        ];

        try {
            $response = $this->http()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $config['api_key'],
                    'Content-Type' => 'application/json',
                ])
                ->timeout(20)
                ->post($config['base_url'] . '/v1/chat/completions', [
                    'model' => $config['model'],
                    'messages' => $messages,
                    'temperature' => 0.2,
                    'max_tokens' => 400,
                ]);
        } catch (\Throwable $e) {
            Log::error('要件整理AI呼び出しに失敗', ['exception' => $e->getMessage()]);
            return response()->json([
                'message' => '要件整理に失敗しました。時間をおいて再度お試しください。',
            ], 500);
        }

        if (!$response->successful()) {
            Log::warning('要件整理AI応答エラー', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return response()->json([
                'message' => '要件整理に失敗しました。',
            ], 500);
        }

        $payload = $this->decodeAiJson(data_get($response->json(), 'choices.0.message.content', ''));
        if (!is_array($payload)) {
            return response()->json([
                'message' => 'AI応答の解析に失敗しました。',
            ], 500);
        }

        $functional = collect($payload['functional_requirements'] ?? [])
            ->filter(static fn($line) => is_string($line) && $line !== '')
            ->map('trim')
            ->values()
            ->all();
        $nonFunctional = collect($payload['non_functional_requirements'] ?? [])
            ->filter(static fn($line) => is_string($line) && $line !== '')
            ->map('trim')
            ->values()
            ->all();
        $unresolved = collect($payload['unresolved_requirements'] ?? [])
            ->filter(static fn($line) => is_string($line) && $line !== '')
            ->map('trim')
            ->values()
            ->all();

        $structured = $this->normalizeStructuredRequirements([
            'functional' => $functional,
            'non_functional' => $nonFunctional,
            'unresolved' => $unresolved,
        ]) ?? ['functional' => [], 'non_functional' => [], 'unresolved' => []];

        if (!empty($validated['estimate_id'])) {
            Estimate::whereKey($validated['estimate_id'])->update([
                'structured_requirements' => $structured,
            ]);
        }

        $this->logAiEvent(
            $validated['estimate_id'] ?? null,
            'structure_requirements',
            array_merge(['summary' => $validated['requirement_summary']], $structured),
            $messages,
            data_get($response->json(), 'choices.0.message.content', '')
        );

        return response()->json([
            'functional_requirements' => $structured['functional'] ?? [],
            'non_functional_requirements' => $structured['non_functional'] ?? [],
            'unresolved_requirements' => $structured['unresolved'] ?? [],
        ]);
    }

    public function generateAiDraft(Request $request)
    {
        $validated = $request->validate([
            'requirement_summary' => 'required|string|max:4000',
            'functional_requirements' => 'nullable|array',
            'functional_requirements.*' => 'string|max:500',
            'non_functional_requirements' => 'nullable|array',
            'non_functional_requirements.*' => 'string|max:500',
            'unresolved_requirements' => 'nullable|array',
            'unresolved_requirements.*' => 'string|max:500',
            'pm_required' => 'required|boolean',
            'estimate_id' => 'nullable|integer|exists:estimates,id',
        ]);

        $productQuery = Product::query()
            ->select(['id', 'name', 'sku', 'price', 'cost', 'unit', 'business_division', 'description'])
            ->where('is_active', true);
        if (Schema::hasColumn('products', 'business_division')) {
            $productQuery->where(function ($query) {
                $query->where('business_division', 'fifth_business')
                    ->orWhere('business_division', '第5種事業')
                    ->orWhere('business_division', 5)
                    ->orWhere('business_division', '5');
            });
        }
        $products = $productQuery->orderBy('name')->get();

        if ($products->isEmpty()) {
            return response()->json([
                'message' => '事業区分5（システム開発）の商品マスタが見つかりません。',
            ], 422);
        }

        $productsById = $products->keyBy('id');
        $catalogLines = $products->map(function ($product) {
            $price = number_format((float) $product->price);
            $summary = trim($product->description ?? '');
            return sprintf('[%d] %s (SKU:%s, 単価:%s円) %s', $product->id, $product->name, $product->sku ?? '-', $price, $summary);
        })->take(40)->implode("\n");

        $pmInstruction = $validated['pm_required']
            ? '必ずPM/プロジェクト管理系の品目を1件含めること。'
            : 'PM系の品目は含めない。';

        $functionalText = collect($validated['functional_requirements'] ?? [])
            ->map(fn($line) => '- ' . $line)
            ->implode("\n");
        $nonFunctionalText = collect($validated['non_functional_requirements'] ?? [])
            ->map(fn($line) => '- ' . $line)
            ->implode("\n");
        $unresolvedText = collect($validated['unresolved_requirements'] ?? [])
            ->map(fn($line) => '- ' . $line)
            ->implode("\n");

        $requirementsBlock = "要件概要:\n{$validated['requirement_summary']}\n\n"
            . ($functionalText ? "機能要件:\n{$functionalText}\n\n" : '')
            . ($nonFunctionalText ? "非機能要件:\n{$nonFunctionalText}\n\n" : '')
            . ($unresolvedText ? "未確定要件:\n{$unresolvedText}\n\n" : '')
            . "数量は常に人日単位（1人日=8時間）。0.5人日刻みで表現し、0.5人日未満は0.5人日に切り上げること。";

        try {
            $config = $this->resolveOpenAiConfig();
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a Japanese sales engineer who proposes system development estimates. '
                    . 'Only use the provided product catalog. '
                    . 'Respond in JSON: { "items": [...], "notes": "..." }. '
                    . 'Each item must have product_id(from catalog), summary(short JP sentence <=40 Japanese characters), person_days(number of person-days) '
                    . 'and optional remarks. Make every summary concrete (feature + scope) and, when requirements mention specific technologies/frameworks/APIs/infrastructure, incorporate those keywords using compact notation (例: React+Nest, SAP連携). '
                    . 'If additional nuance is needed, populate the optional remarks (<=40 Japanese characters) focusing on critical stack・連携条件. Items should cover設計/開発/テスト/PM at 3〜6 entries. '
                    . 'Create separate items for each distinct feature or screen even if the same product is reused, and include the feature name inside the summary (e.g., 「開発｜ダッシュボード」). '
                    . 'For UI/UX related tasks, split admin-side and end-user screens into different items when the requirements imply multiple audiences. '
                    . 'Include only one testing line named 「総合テスト」 and do not output other test types. '
                    . 'The notes field must be written in polite Japanese with multiple sections using full-width bracket headings '
                    . 'such as 【検収基準】, 【納期】, 【前提条件】, 【変更管理】, 【保守保証】. '
                    . 'Each section should contain 1〜2 sentences, separated by blank lines, and should omit bullet symbols.',
            ],
            [
                'role' => 'user',
                'content' => implode("\n\n", [
                    "利用可能な商品一覧:\n{$catalogLines}",
                    $pmInstruction,
                    $requirementsBlock,
                    'JSON形式の例: {"items":[{"product_id":12,"summary":"UI設計｜管理画面","person_days":10},{"product_id":12,"summary":"UI設計｜エンドユーザ画面","person_days":8},{"product_id":13,"summary":"開発｜ダッシュボード","person_days":25},{"product_id":14,"summary":"総合テスト","person_days":5}],"notes":"任意の注意書き"}。単価や原価は返さない。',
                ]),
            ],
        ];

        try {
            $response = $this->http()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $config['api_key'],
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post($config['base_url'] . '/v1/chat/completions', [
                    'model' => $config['model'],
                    'messages' => $messages,
                    'temperature' => 0.4,
                    'max_tokens' => 900,
                ]);
        } catch (\Throwable $e) {
            Log::error('AIドラフト生成呼び出し失敗', ['exception' => $e->getMessage()]);
            return response()->json([
                'message' => 'ドラフト生成に失敗しました。時間を置いて再度お試しください。',
            ], 500);
        }

        if (!$response->successful()) {
            Log::warning('AIドラフト生成応答エラー', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return response()->json([
                'message' => 'ドラフト生成に失敗しました。',
            ], 500);
        }

        $rawContent = data_get($response->json(), 'choices.0.message.content', '');
        $payload = $this->decodeAiJson($rawContent);
        if (!is_array($payload) || empty($payload['items'])) {
            return response()->json([
                'message' => 'AI応答の解析に失敗しました。',
            ], 500);
        }

        $aiItems = [];
        foreach ($payload['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
            $product = $productId ? ($productsById[$productId] ?? null) : null;
            if (!$product) {
                continue;
            }

            $personDays = $this->toFloat($item['person_days'] ?? null);
            if ($personDays === null || $personDays <= 0) {
                continue;
            }
            $normalizedPersonDays = $this->normalizePersonDays($personDays);
            if ($normalizedPersonDays === null) {
                continue;
            }

            $description = $this->resolveFeatureDescription(
                $item['summary'] ?? null,
                $product->name,
                $product->description,
                $item['detail'] ?? ($item['remarks'] ?? null)
            );

            $aiItems[] = [
                'product_id' => $product->id,
                'code' => $product->sku,
                'name' => $product->name,
                'description' => $description,
                'qty' => $normalizedPersonDays,
                'unit' => '人日',
                'price' => (float) $product->price,
                'cost' => (float) $product->cost,
                'tax_category' => 'standard',
                'business_division' => $product->business_division,
            ];
        }

        if (empty($aiItems)) {
            return response()->json([
                'message' => '有効な品目を生成できませんでした。要件の補足を行ってください。',
            ], 422);
        }

        $notes = trim((string) ($payload['notes'] ?? '')) ?: null;

        $this->logAiEvent(
            $validated['estimate_id'] ?? null,
            'generate_draft',
            [
                'summary' => $validated['requirement_summary'],
                'functional' => $validated['functional_requirements'] ?? [],
                'non_functional' => $validated['non_functional_requirements'] ?? [],
                'unresolved' => $validated['unresolved_requirements'] ?? [],
                'pm_required' => (bool) $validated['pm_required'],
            ],
            $messages,
            $rawContent
        );

        return response()->json([
            'items' => $aiItems,
            'notes' => $notes,
        ]);
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

        try {
            $response = $this->http()->withHeaders([
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
        try {
            $this->syncPartnerContactWithMoneyForward($estimate, $token, $apiService);
        } catch (\Throwable $e) {
            Log::warning('Failed to sync department contact before quote creation.', [
                'estimate_id' => $estimate->id,
                'error' => $e->getMessage(),
            ]);
        }

        $result = $apiService->createQuoteFromEstimate($estimate, $token);

        if ($result && isset($result['id'])) {
            $estimate->mf_quote_id = $result['id'];
            if (isset($result['pdf_url'])) {
                $estimate->mf_quote_pdf_url = $result['pdf_url'];
            }
            if (Schema::hasColumn('estimates', 'mf_deleted_at')) {
                $estimate->mf_deleted_at = null;
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
            $this->estimateDetailUrl($estimate)
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
            $this->estimateDetailUrl($estimate)
        );

        $this->sendChatNotification($webhook, $message);
    }

    private function notifyApprovalRejected(Estimate $estimate, array $step, ?User $actor = null, ?string $reason = null): void
    {
        $webhook = (string) config('services.google_chat.approval_webhook', '');
        if ($webhook === '') {
            return;
        }

        $rejector = $step['name'] ?? $actor?->name ?? '承認者';
        $reasonLine = $reason ? "\n理由: " . $reason : '';
        $message = sprintf(
            "承認却下: %s\n却下者: %s%s\nURL: %s",
            $estimate->estimate_number,
            $rejector,
            $reasonLine,
            $this->estimateDetailUrl($estimate)
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
            $this->estimateDetailUrl($estimate)
        );

        $this->sendChatNotification($webhook, $message);
    }

    private function notifyOrderConfirmed(Estimate $estimate, ?User $initiator = null): void
    {
        $webhook = (string) config('services.google_chat.approval_webhook', '');
        if ($webhook === '') {
            return;
        }

        $message = sprintf(
            "受注確定: %s\n件名: %s\n顧客: %s\n確定者: %s\nURL: %s",
            $estimate->estimate_number,
            $estimate->title ?? '（件名未設定）',
            $estimate->customer_name ?? '（顧客未設定）',
            $initiator?->name ?? 'システム',
            $this->estimateDetailUrl($estimate)
        );

        $this->sendChatNotification($webhook, $message);
    }

    private function notifyApprovalCancelled(Estimate $estimate, ?User $initiator = null): void
    {
        $webhook = (string) config('services.google_chat.approval_webhook', '');
        if ($webhook === '') {
            return;
        }

        $message = sprintf(
            "承認取消: %s\n件名: %s\n取消者: %s\nURL: %s",
            $estimate->estimate_number,
            $estimate->title ?? '（件名未設定）',
            $initiator?->name ?? 'システム',
            $this->estimateDetailUrl($estimate)
        );

        $this->sendChatNotification($webhook, $message);
    }

    private function estimateDetailUrl(Estimate $estimate): string
    {
        return route('quotes.index', ['estimate_id' => $estimate->id]);
    }

    private function sendChatNotification(string $webhook, string $message): void
    {
        try {
            $response = $this->http()->timeout(5)->post($webhook, ['text' => $message]);
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
            $status = $step['status'] ?? (empty($step['approved_at']) ? 'pending' : 'approved');
            if ($status === 'pending') {
                $name = $step['name'] ?? null;
                if ($name) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    private function buildCompanyProfile(): array
    {
        $companyConfig = (array) config('company', []);

        $companyName = trim((string) ($companyConfig['name'] ?? ''));
        $companyAddress = trim((string) ($companyConfig['address'] ?? ''));
        $companyPhone = trim((string) ($companyConfig['phone'] ?? ''));
        $companyEmail = trim((string) ($companyConfig['email'] ?? ''));
        $companyWebsite = trim((string) ($companyConfig['website'] ?? ''));

        return [
            'name' => $companyName !== ''
                ? $companyName
                : config('app.name', '熊本コンピュータソフト株式会社'),
            'address' => $companyAddress !== ''
                ? $companyAddress
                : "〒862-0976\n熊本県熊本市中央区九品寺5丁目8-9",
            'phone' => $companyPhone !== '' ? $companyPhone : null,
            'email' => $companyEmail !== '' ? $companyEmail : null,
            'website' => $companyWebsite !== ''
                ? $companyWebsite
                : config('app.url'),
        ];
    }

    private function buildClientProfile(Estimate $estimate): array
    {
        return [
            'name' => $estimate->customer_name ?? '',
            'address' => $this->resolveClientAddress($estimate),
            'contact_name' => $estimate->client_contact_name,
            'contact_title' => $estimate->client_contact_title,
        ];
    }

    private function resolveClientAddress(Estimate $estimate): ?string
    {
        $partnerId = $estimate->client_id;
        if (!empty($partnerId)) {
            $partner = Partner::where('mf_partner_id', (string) $partnerId)->first();
            if ($partner && is_array($partner->payload)) {
                if (!empty($estimate->mf_department_id)) {
                    $department = $this->findPartnerNodeById($partner->payload, (string) $estimate->mf_department_id);
                    if ($department) {
                        $formatted = $this->formatPartnerAddress($department);
                        if ($formatted) {
                            return $formatted;
                        }
                    }
                }

                $fallback = $this->formatPartnerAddress($partner->payload);
                if ($fallback) {
                    return $fallback;
                }
            }
        }

        if (!empty($estimate->delivery_location)) {
            return $estimate->delivery_location;
        }

        return null;
    }

    private function findPartnerNodeById(?array $payload, string $targetId): ?array
    {
        if (!is_array($payload)) {
            return null;
        }

        $stack = [$payload];
        while ($stack) {
            $node = array_pop($stack);
            if (!is_array($node)) {
                continue;
            }

            if (isset($node['id']) && (string) $node['id'] === $targetId) {
                return $node;
            }

            foreach ($node as $value) {
                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }

        return null;
    }

    private function formatPartnerAddress(?array $node): ?string
    {
        if (!is_array($node)) {
            return null;
        }

        $addressSource = $node;
        if (isset($node['address']) && is_array($node['address'])) {
            $addressSource = array_merge($node, $node['address']);
        }

        $zip = $this->firstNotEmpty($addressSource, ['zip', 'zip_code', 'postal_code']);
        $lines = [];
        if ($zip) {
            $digits = preg_replace('/[^0-9]/', '', (string) $zip);
            if (strlen($digits) === 7) {
                $zip = substr($digits, 0, 3) . '-' . substr($digits, 3);
            }
            $lines[] = '〒' . $zip;
        }

        $addressParts = $this->collectAddressParts($addressSource);
        if ($addressParts) {
            $lines[] = implode('', $addressParts);
        }

        if (empty($lines) && isset($node['addresses']) && is_array($node['addresses'])) {
            foreach ($node['addresses'] as $nested) {
                $formatted = $this->formatPartnerAddress($nested);
                if ($formatted) {
                    return $formatted;
                }
            }
        }

        return $lines ? implode("\n", $lines) : null;
    }

    private function collectAddressParts(array $node): array
    {
        $parts = [];
        $orderedKeys = [
            'prefecture', 'prefecture_name', 'city', 'ward', 'town', 'street',
            'address1', 'address2', 'address3', 'line1', 'line2', 'line3',
        ];

        foreach ($orderedKeys as $key) {
            if (!empty($node[$key])) {
                $parts[] = trim((string) $node[$key]);
            }
        }

        if (isset($node['address']) && is_string($node['address']) && trim($node['address']) !== '') {
            $parts[] = trim($node['address']);
        }

        return $parts;
    }

    private function firstNotEmpty(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!empty($source[$key])) {
                return (string) $source[$key];
            }
        }
        return null;
    }

    private function resolveCompanyLogoUrl(): ?string
    {
        $logoPath = resource_path('imgs/kcs_logo.png');
        if (!is_file($logoPath)) {
            return null;
        }

        try {
            return Vite::asset('resources/imgs/kcs_logo.png');
        } catch (\Throwable $e) {
            Log::warning('注文書ロゴのアセット解決に失敗しました。', [
                'error' => $e->getMessage(),
            ]);

            $resourcePath = resource_path('imgs/kcs_logo.png');
            if (is_file($resourcePath)) {
                try {
                    return 'data:image/png;base64,' . base64_encode(file_get_contents($resourcePath));
                } catch (\Throwable $inner) {
                    Log::warning('注文書ロゴのローカル読込に失敗しました。', [
                        'error' => $inner->getMessage(),
                    ]);
                }
            }

            return null;
        }
    }

    private function generatePurchaseOrderNumberForPreview(Estimate $estimate): string
    {
        $estimateNumber = trim((string) ($estimate->estimate_number ?? ''));
        if ($estimateNumber !== '') {
            return 'PO-' . $estimateNumber;
        }

        return sprintf('PO-%06d', $estimate->id);
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
    private function deleteMoneyForwardQuote(?string $mfQuoteId): void
    {
        if (!$mfQuoteId) {
            return;
        }

        $tokens = MfToken::query()->pluck('access_token')->filter();
        foreach ($tokens as $accessToken) {
            try {
                $client = new \GuzzleHttp\Client();
                $response = $client->delete(config('services.money_forward.api_url') . "/quotes/{$mfQuoteId}", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Accept' => 'application/json',
                    ],
                ]);
                if ($response->getStatusCode() < 500) {
                    break;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
    }

    private function clearMoneyForwardQuoteState(Estimate $estimate, bool $markDeleted = true): void
    {
        $estimate->mf_quote_id = null;
        $estimate->mf_quote_pdf_url = null;
        if (Schema::hasColumn('estimates', 'mf_invoice_id')) {
            $estimate->mf_invoice_id = null;
        }
        if (Schema::hasColumn('estimates', 'mf_invoice_pdf_url')) {
            $estimate->mf_invoice_pdf_url = null;
        }
        if ($markDeleted && Schema::hasColumn('estimates', 'mf_deleted_at')) {
            $estimate->mf_deleted_at = now();
        }
    }

    private function extractPmCustomerId(?string $source): ?int
    {
        if (empty($source)) {
            return null;
        }

        if (preg_match('/(\d+)/', (string) $source, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function fetchMaintenanceTotal(Carbon $month = null): float
    {
        $month = $month ?? Carbon::now();
        $monthKey = $month->copy()->startOfMonth()->toDateString();
        $cacheKey = "maintenance_fee_total_quotes_{$monthKey}";

        // スナップショット最優先
        if (Schema::hasTable('maintenance_fee_snapshots')) {
            $snap = \App\Models\MaintenanceFeeSnapshot::where('month', $monthKey)->first();
            if ($snap) {
                Cache::put($cacheKey, (float) $snap->total_fee, 300);
                return (float) ($snap->total_fee ?? 0);
            }
        }

        if (Cache::has($cacheKey)) {
            return (float) Cache::get($cacheKey);
        }

        $base = rtrim((string) env('EXTERNAL_API_BASE', 'https://api.xerographix.co.jp/public/api'), '/');
        $token = (string) env('EXTERNAL_API_TOKEN', '');

        $total = 0.0;
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => $token ? 'Bearer ' . $token : null,
            ])->withOptions([
                'verify' => env('SSL_VERIFY', true),
            ])->get($base . '/customers');

            if ($response->successful()) {
                $customers = $response->json();
                if (is_array($customers)) {
                    foreach ($customers as $c) {
                        $fee = (float) ($c['maintenance_fee'] ?? 0);
                        if ($fee <= 0) continue;
                        $status = (string) ($c['status'] ?? $c['customer_status'] ?? $c['status_name'] ?? '');
                        if ($status !== '' && (mb_stripos($status, '休止') !== false || mb_strtolower($status) === 'inactive')) continue;
                        $total += $fee;
                    }
                }
            } else {
                \Log::warning('Failed to fetch maintenance customers for quotes', [
                    'status' => $response->status(),
                    'url' => $base . '/customers',
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            \Log::error('Error fetching maintenance customers for quotes', [
                'message' => $e->getMessage(),
            ]);
        }

        if (Schema::hasTable('maintenance_fee_snapshots')) {
            \App\Models\MaintenanceFeeSnapshot::updateOrCreate(
                ['month' => $monthKey],
                ['total_fee' => $total, 'total_gross' => $total, 'source' => 'api']
            );
        }

        Cache::put($cacheKey, $total, 300);
        return $total;
    }
}
