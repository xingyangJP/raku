<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Estimate;

class EstimateController extends Controller
{
    public function create()
    {
        $products = [
            ['id' => 1, 'name' => 'システム設計', 'price' => 100000, 'cost' => 50000],
            ['id' => 2, 'name' => 'インフラ構築', 'price' => 200000, 'cost' => 100000],
            ['id' => 3, 'name' => 'DB設計', 'price' => 150000, 'cost' => 75000],
            ['id' => 4, 'name' => '要件定義', 'price' => 80000, 'cost' => 40000],
            ['id' => 5, 'name' => 'テスト', 'price' => 60000, 'cost' => 30000],
        ];

        return Inertia::render('Estimates/Create', [
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'issue_date' => 'required|date',
            'due_date' => 'required|date',
            'total_amount' => 'required|integer',
            'tax_amount' => 'required|integer',
            'notes' => 'nullable|string',
            'items' => 'required|array',
            'estimate_number' => 'required|string|max:255|unique:estimates,estimate_number', // Added validation for estimate_number
        ]);

        $estimate = Estimate::create(array_merge($validated, ['status' => 'sent']));

        return redirect()->route('estimates.show', $estimate->id)
            ->with('success', 'Estimate created successfully.');
    }

    public function saveDraft(Request $request)
    {
        $rules = [
            'customer_name' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'issue_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'total_amount' => 'nullable|integer',
            'tax_amount' => 'nullable|integer',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
        ];

        $estimateNumber = $request->input('estimate_number');
        $estimate = null;

        if ($estimateNumber) {
            $estimate = Estimate::where('estimate_number', $estimateNumber)->first();
            $estimateNumberRule = 'required|string|max:255|unique:estimates,estimate_number';
            if ($estimate) {
                $estimateNumberRule .= ',' . $estimate->id;
            }
            $rules['estimate_number'] = $estimateNumberRule;
        } else {
            $rules['estimate_number'] = 'nullable|string|max:255';
        }

        $validated = $request->validate($rules);

        if ($estimate) {
            // Update existing estimate
            $estimate->update(array_merge($validated, ['status' => 'draft']));
        } else {
            // Create new estimate
            $estimate = Estimate::create(array_merge($validated, ['status' => 'draft']));
        }

        return redirect()->back()->with('success', 'Draft saved successfully.');
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
        $products = [
            ['id' => 1, 'name' => 'システム設計', 'price' => 100000, 'cost' => 50000],
            ['id' => 2, 'name' => 'インフラ構築', 'price' => 200000, 'cost' => 100000],
            ['id' => 3, 'name' => 'DB設計', 'price' => 150000, 'cost' => 75000],
            ['id' => 4, 'name' => '要件定義', 'price' => 80000, 'cost' => 40000],
            ['id' => 5, 'name' => 'テスト', 'price' => 60000, 'cost' => 30000],
        ];

        return Inertia::render('Estimates/Create', [
            'estimate' => $estimate,
            'products' => $products,
        ]);
    }

    public function duplicate(Estimate $estimate)
    {
        $newEstimate = $estimate->replicate();
        $newEstimate->estimate_number = $this->generateUniqueEstimateNumber($estimate->estimate_number);
        $newEstimate->status = 'draft'; // Set status to draft for duplicated estimate
        $newEstimate->save();

        return redirect()->route('estimates.edit', $newEstimate->id)
            ->with('success', '見積書を複製しました。');
    }

    private function generateUniqueEstimateNumber($originalNumber)
    {
        $i = 1;
        $newNumber = $originalNumber . '-copy';
        while (Estimate::where('estimate_number', $newNumber)->exists()) {
            $newNumber = $originalNumber . '-copy-' . $i;
            $i++;
        }
        return $newNumber;
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
            // 'new_staff_id' => 'required|exists:users,id', // Uncomment and implement when frontend is ready
        ]);

        // Placeholder for reassign logic
        // Estimate::whereIn('id', $request->ids)->update(['staff_id' => $request->new_staff_id]);

        return redirect()->back()->with('success', count($request->ids) . '件の見積書の担当者付替を処理しました。');
    }

    public function update(Request $request, Estimate $estimate)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'issue_date' => 'required|date',
            'due_date' => 'required|date',
            'total_amount' => 'required|integer',
            'tax_amount' => 'required|integer',
            'notes' => 'nullable|string',
            'items' => 'required|array',
            'estimate_number' => 'required|string|max:255|unique:estimates,estimate_number,' . $estimate->id,
        ]);

        $estimate->update($validated);

        return redirect()->route('quotes.index')->with('success', 'Quote updated successfully.');
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
