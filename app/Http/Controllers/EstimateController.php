<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Estimate;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class EstimateController extends Controller
{
    private function loadProducts()
    {
        // Prefer DB-backed products if table exists; otherwise fallback to in-memory samples
        if (Schema::hasTable('products')) {
            return DB::table('products')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'price', 'cost', 'unit', 'sku']);
        }
        return [
            ['id' => 1, 'name' => 'システム設計', 'price' => 100000, 'cost' => 50000, 'unit' => '式'],
            ['id' => 2, 'name' => 'インフラ構築', 'price' => 200000, 'cost' => 100000, 'unit' => '式'],
            ['id' => 3, 'name' => 'DB設計', 'price' => 150000, 'cost' => 75000, 'unit' => '式'],
            ['id' => 4, 'name' => '要件定義', 'price' => 80000, 'cost' => 40000, 'unit' => '式'],
            ['id' => 5, 'name' => 'テスト', 'price' => 60000, 'cost' => 30000, 'unit' => '式'],
        ];
    }

    public function create()
    {
        $products = $this->loadProducts();

        return Inertia::render('Estimates/Create', [
            'products' => $products,
            // users fetched via external API on client
        ]);
    }

    public function store(Request $request)
    {
        // Normalize client_id to string if numeric
        $clientId = $request->input('client_id');
        if (!is_null($clientId) && !is_string($clientId)) {
            $request->merge(['client_id' => (string) $clientId]);
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'client_id' => 'nullable|string|max:255',
            'title' => 'required|string|max:255',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'total_amount' => 'required|integer',
            'tax_amount' => 'required|integer',
            'notes' => 'nullable|string',
            'items' => 'required|array',
            'estimate_number' => 'nullable|string|max:255|unique:estimates,estimate_number',
            'staff_id' => 'nullable|integer',
            'staff_name' => 'nullable|string|max:255',
            'approval_flow' => 'nullable|array',
            'status' => 'nullable|string|in:draft,pending,sent,rejected',
        ]);

        // Normalize dates to Y-m-d
        if (!empty($validated['issue_date'])) {
            $validated['issue_date'] = date('Y-m-d', strtotime($validated['issue_date']));
        }
        if (!empty($validated['due_date'])) {
            $validated['due_date'] = date('Y-m-d', strtotime($validated['due_date']));
        }

        // Drop approval_flow safely if migration not yet applied
        if (!Schema::hasColumn('estimates', 'approval_flow')) {
            unset($validated['approval_flow']);
        }

        if (empty($validated['estimate_number'])) {
            $validated['estimate_number'] = $this->generateReadableEstimateNumber(
                $validated['staff_id'] ?? null,
                $validated['client_id'] ?? null,
                false
            );
        }
        $status = $validated['status'] ?? 'sent';
        unset($validated['status']);
        $estimate = Estimate::create(array_merge($validated, ['status' => $status]));

        $products = $this->loadProducts();

        // After creating, we render the edit page (Create component) with the new estimate data
        // This allows the UI to update without a full redirect, preserving modal state.
        return Inertia::render('Estimates/Create', [
            'estimate' => $estimate->fresh(),
            'products' => $products,
            'approval_success_message' => '承認申請を開始しました。'
        ]);
    }

    public function apply(Request $request, Estimate $estimate)
    {
        $validated = $request->validate([
            'approval_flow' => 'required|array',
        ]);

        $estimate->status = 'pending';
        $estimate->approval_flow = $validated['approval_flow'];
        $estimate->save();

        // Re-render the component to trigger onSuccess on the frontend
        $products = $this->loadProducts();

        return Inertia::render('Estimates/Create', [
            'estimate' => $estimate->fresh(),
            'products' => $products,
            'approval_success_message' => '承認申請を開始しました。'
        ]);
    }

    public function saveDraft(Request $request)
    {
        // Normalize client_id to string if numeric
        $clientId = $request->input('client_id');
        if (!is_null($clientId) && !is_string($clientId)) {
            $request->merge(['client_id' => (string) $clientId]);
        }

        $rules = [
            'customer_name' => 'nullable|string|max:255',
            'client_id' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'issue_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'total_amount' => 'nullable|integer',
            'tax_amount' => 'nullable|integer',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            // For drafts, allow any integer; we normalized invalid IDs to a valid user or null above
            'staff_id' => 'nullable|integer',
            'staff_name' => 'nullable|string|max:255',
            'approval_flow' => 'nullable|array',
        ];

        $estimateNumber = $request->input('estimate_number');
        $estimate = null;

        // If estimate_number is missing, generate a draft number (column is NOT NULL)
        if (!$estimateNumber) {
            $estimateNumber = $this->generateReadableEstimateNumber(
                $request->input('staff_id'),
                $request->input('client_id'),
                true
            );
            $request->merge(['estimate_number' => $estimateNumber]);
        }

        if ($estimateNumber) {
            $estimate = Estimate::where('estimate_number', $estimateNumber)->first();
            $estimateNumberRule = 'required|string|max:255|unique:estimates,estimate_number';
            if ($estimate) {
                $estimateNumberRule .= ',' . $estimate->id;
            }
            $rules['estimate_number'] = $estimateNumberRule;
        }

        $validated = $request->validate($rules);
        // Normalize dates to Y-m-d if present
        if (!empty($validated['issue_date'])) {
            $validated['issue_date'] = date('Y-m-d', strtotime($validated['issue_date']));
        }
        if (!empty($validated['due_date'])) {
            $validated['due_date'] = date('Y-m-d', strtotime($validated['due_date']));
        }

        // Drop approval_flow safely if migration not yet applied
        if (!Schema::hasColumn('estimates', 'approval_flow')) {
            unset($validated['approval_flow']);
        }

        if ($estimate) {
            // Update existing estimate
            $estimate->update(array_merge($validated, ['status' => 'draft']));
        } else {
            // Create new estimate
            $estimate = Estimate::create(array_merge($validated, ['status' => 'draft']));
        }

        return redirect()->back()->with('success', 'Draft saved successfully.');
    }

    private function generateReadableEstimateNumber($staffId, $clientId, bool $draft): string
    {
        // Format: EST-{staff}-{client}-{yyddmm}-{seq}  (PHP date: y=YY, d=DD, m=MM)
        $date = now()->format('ydm');
        $staff = $staffId ?: 'X';
        $client = $clientId ?: 'X';
        $kind = $draft ? 'EST-D' : 'EST';
        $prefix = "$kind-$staff-$client-$date-";
        $latest = Estimate::where('estimate_number', 'like', $prefix.'%')
            ->orderBy('estimate_number', 'desc')
            ->first();
        $seq = 1;
        if ($latest) {
            $tail = substr($latest->estimate_number, strlen($prefix));
            $num = (int) $tail;
            $seq = $num + 1;
        }
        return $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
    }

    public function index()
    {
        $estimates = Estimate::orderByDesc('updated_at')->get();
        return Inertia::render('Quotes/Index', [
            'estimates' => $estimates,
        ]);
    }

    public function edit(Estimate $estimate)
    {
        $products = $this->loadProducts();

        return Inertia::render('Estimates/Create', [
            'estimate' => $estimate,
            'products' => $products,
        ]);
    }

    public function duplicate(Estimate $estimate)
    {
        $newEstimate = $estimate->replicate();
        $newEstimate->estimate_number = $this->generateReadableEstimateNumber(
            $estimate->staff_id ?? null,
            $estimate->client_id ?? null,
            true
        );
        $newEstimate->status = 'draft';
        $newEstimate->save();

        return redirect()->route('estimates.edit', $newEstimate->id)
            ->with('success', '見積書を複製しました。');
    }

    // generateUniqueEstimateNumber obsolete; unified to generateReadableEstimateNumber

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
            // 'new_staff_id' => 'required|exists:users,id', // Uncomment and implement when frontend is ready
        ]);

        // Placeholder for reassign logic
        // Estimate::whereIn('id', $request->ids)->update(['staff_id' => $request->new_staff_id]);

        return redirect()->back()->with('success', count($request->ids) . '件の見積書の担当者付替を処理しました。');
    }

    public function update(Request $request, Estimate $estimate)
    {
        // Normalize client_id to string if numeric
        $clientId = $request->input('client_id');
        if (!is_null($clientId) && !is_string($clientId)) {
            $request->merge(['client_id' => (string) $clientId]);
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'client_id' => 'nullable|string|max:255',
            'title' => 'required|string|max:255',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date',
            'total_amount' => 'required|integer',
            'tax_amount' => 'required|integer',
            'notes' => 'nullable|string',
            'items' => 'required|array',
            'estimate_number' => 'required|string|max:255|unique:estimates,estimate_number,' . $estimate->id,
            'staff_id' => 'nullable|integer',
            'staff_name' => 'nullable|string|max:255',
            'approval_flow' => 'nullable|array',
            'status' => 'nullable|string|in:draft,pending,sent,rejected',
        ]);

        // Normalize dates to Y-m-d
        if (!empty($validated['issue_date'])) {
            $validated['issue_date'] = date('Y-m-d', strtotime($validated['issue_date']));
        }
        if (!empty($validated['due_date'])) {
            $validated['due_date'] = date('Y-m-d', strtotime($validated['due_date']));
        }

        $status = $validated['status'] ?? $estimate->status;
        unset($validated['status']);
        // Drop approval_flow safely if migration not yet applied
        if (!Schema::hasColumn('estimates', 'approval_flow')) {
            unset($validated['approval_flow']);
        }
        $estimate->update(array_merge($validated, ['status' => $status]));

        $flash = [];
        if ($status === 'pending') {
            $flash['approval_started'] = true;
            if (!empty($validated['approval_flow'])) {
                $flash['approval_flow'] = $validated['approval_flow'];
            }
        } else {
            $flash['success'] = 'Quote updated successfully.';
        }

        $products = $this->loadProducts();

        return Inertia::render('Estimates/Create', [
            'estimate' => $estimate->fresh(),
            'products' => $products,
            'approval_success_message' => '承認申請を開始しました。'
        ]);
    }

    public function destroy(Estimate $estimate)
    {
        $estimate->delete();
        return redirect()->route('quotes.index')->with('success', '見積書を削除しました。');
    }

    public function previewPdf(Request $request)
    {
        $estimateData = $request->all();

        // For web preview
        return view('estimates.pdf', compact('estimateData'));

        // For PDF generation
        // $pdf = Pdf::loadView('estimates.pdf', compact('estimateData'));
        // return $pdf->stream('estimate_preview.pdf');
    }
}
