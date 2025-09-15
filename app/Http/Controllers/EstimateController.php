<?php

namespace App\Http\Controllers;

use App\Models\Estimate;
use App\Models\Partner;
use App\Models\User;
use App\Services\MoneyForwardApiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;

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

    public function index()
    {
        $estimates = Estimate::orderByDesc('issue_date')
            ->orderByDesc('estimate_number')
            ->orderByDesc('id')
            ->get();
        return Inertia::render('Quotes/Index', [
            'estimates' => $estimates,
        ]);
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
        if ($estimate->status === 'sent' && $request->input('status') === 'pending') {
            return redirect()->route('estimates.edit', $estimate->id)->with('error', 'This estimate is already approved.');
        }
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
        if ($token = $apiService->getValidAccessToken()) {
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
        if ($token = $apiService->getValidAccessToken()) {
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
        if ($token = $apiService->getValidAccessToken()) {
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
        $scope = 'mfc/invoice/data.read'; // Default scope
        if (in_array($action, ['create_quote', 'convert_to_billing'])) {
            $scope = 'mfc/invoice/data.write';
        }

        $authUrl = config('services.money_forward.authorization_url') . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.money_forward.client_id'),
            'redirect_uri' => route('estimates.auth.callback'),
            'scope' => $scope,
        ]);
        return Inertia::location($authUrl);
    }

    public function handleCallback(Request $request, MoneyForwardApiService $apiService)
    {
        if (!$request->has('code')) {
            return redirect()->route('quotes.index')->with('error', 'Authorization failed.');
        }

        $tokenData = $apiService->getAccessTokenFromCode($request->code, route('estimates.auth.callback'));
        if (!$tokenData) {
            return redirect()->route('quotes.index')->with('error', 'Failed to get access token.');
        }

        $apiService->storeToken($tokenData, Auth::id());
        $token = $tokenData['access_token'];

        $action = $request->session()->get('mf_redirect_action');
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
}
