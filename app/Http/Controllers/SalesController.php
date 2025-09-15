<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Carbon\Carbon;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today();
        $paidStatuses = ['入金済','完了','paid','Paid','支払済'];

        $items = [];
        $summary = ['current_total' => 0, 'overdue_total' => 0];

        if (Schema::hasTable('billings')) {
            $rows = DB::table('billings')
                ->select('id', 'partner_name', 'title', 'billing_number', 'due_date', 'total_price', 'payment_status')
                ->where(function ($q) use ($paidStatuses) {
                    $q->whereNull('payment_status')->orWhereNotIn('payment_status', $paidStatuses);
                })
                ->whereNotNull('total_price')
                ->get();

            foreach ($rows as $r) {
                $due = $r->due_date ? Carbon::parse($r->due_date) : null;
                $isOverdue = $due ? $due->lt($today) : false; // 期日未設定は未超過扱い
                $amount = (int) round((float) ($r->total_price ?? 0));
                $items[] = [
                    'id' => (string) $r->id,
                    'partner_name' => (string) ($r->partner_name ?? ''),
                    'title' => (string) ($r->title ?? ''),
                    'billing_number' => (string) ($r->billing_number ?? ''),
                    'due_date' => $due ? $due->format('Y-m-d') : null,
                    'total_price' => $amount,
                    'payment_status' => (string) ($r->payment_status ?? ''),
                    'category' => $isOverdue ? '期日超過売掛' : '売掛',
                ];
                if ($isOverdue) { $summary['overdue_total'] += $amount; }
                else { $summary['current_total'] += $amount; }
            }
        }

        return Inertia::render('Sales/Index', [
            'summary' => $summary,
            'items' => $items,
        ]);
    }
}

