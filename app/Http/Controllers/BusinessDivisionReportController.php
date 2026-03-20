<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BusinessDivisionReportController extends Controller
{
    public function index(Request $request)
    {
        return redirect()
            ->route('dashboard')
            ->with('success', '事業区分分析はダッシュボードへ統合しました。');
    }

    public function updateProductDivision(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'business_division' => ['required', 'string', 'in:' . implode(',', array_keys(config('business_divisions.options', [])))],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->with('error', '事業区分の更新に失敗しました。');
        }

        $division = $validator->validated()['business_division'];

        $product->business_division = $division;
        $product->save();

        return redirect()->back()->with('success', "{$product->name} の事業区分を更新しました。");
    }
}
