<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

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

    public function previewPdf(Request $request)
    {
        $estimateData = $request->all();

        $pdf = Pdf::loadView('estimates.pdf', compact('estimateData'));

        return $pdf->stream('estimate_preview.pdf');
    }
}
