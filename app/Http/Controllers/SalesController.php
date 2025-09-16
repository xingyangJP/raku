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
        // Month range filter: from/to as YYYY-MM
        $fromMonth = (string) $request->query('from', Carbon::now()->subMonth()->format('Y-m'));
        $toMonth = (string) $request->query('to', Carbon::now()->format('Y-m'));
        $fromDate = Carbon::createFromFormat('Y-m', $fromMonth)->startOfMonth();
        $toDate = Carbon::createFromFormat('Y-m', $toMonth)->endOfMonth();

        $items = [];
        $summary = ['current_total' => 0, 'overdue_total' => 0];

        // Source 1: MF billings table
        if (Schema::hasTable('billings')) {
            $rows = DB::table('billings')
                ->select('id', 'partner_name', 'title', 'billing_number', 'billing_date', 'due_date', 'total_price', 'payment_status')
                ->where(function ($q) use ($paidStatuses) {
                    $q->whereNull('payment_status')->orWhereNotIn('payment_status', $paidStatuses);
                })
                ->whereNotNull('total_price')
                ->when(true, function ($q) use ($fromDate, $toDate) {
                    $q->whereBetween('billing_date', [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')]);
                })
                ->get();

            foreach ($rows as $r) {
                $due = $r->due_date ? Carbon::parse($r->due_date) : null;
                $isOverdue = $due ? $due->lt($today) : false; // 期日未設定は未超過扱い
                $amount = (int) round((float) ($r->total_price ?? 0));
                $items[] = [
                    'id' => 'mf-' . (string) $r->id,
                    'partner_name' => (string) ($r->partner_name ?? ''),
                    'title' => (string) ($r->title ?? ''),
                    'billing_number' => (string) ($r->billing_number ?? ''),
                    'due_date' => $due ? $due->format('Y-m-d') : null,
                    'total_price' => $amount,
                    'payment_status' => (string) ($r->payment_status ?? ''),
                    'category' => $isOverdue ? '期日超過売掛' : '売掛',
                ];
                if ($isOverdue) { $summary['overdue_total'] += $amount; } else { $summary['current_total'] += $amount; }
            }
        }

        // Source 2: Local invoices (draft/final, not linked to payments)
        if (Schema::hasTable('local_invoices')) {
            $localRows = DB::table('local_invoices')
                ->select('id', 'customer_name as partner_name', 'title', 'billing_number', 'billing_date', 'due_date', 'total_amount as total_price')
                ->whereBetween('billing_date', [$fromDate->format('Y-m-d'), $toDate->format('Y-m-d')])
                ->get();

            foreach ($localRows as $r) {
                $due = $r->due_date ? Carbon::parse($r->due_date) : null;
                $isOverdue = $due ? $due->lt($today) : false;
                $amount = (int) round((float) ($r->total_price ?? 0));
                $items[] = [
                    'id' => 'local-' . (string) $r->id,
                    'partner_name' => (string) ($r->partner_name ?? ''),
                    'title' => (string) ($r->title ?? ''),
                    'billing_number' => (string) ($r->billing_number ?? ''),
                    'due_date' => $due ? $due->format('Y-m-d') : null,
                    'total_price' => $amount,
                    'payment_status' => '',
                    'category' => $isOverdue ? '期日超過売掛' : '売掛',
                ];
                if ($isOverdue) { $summary['overdue_total'] += $amount; } else { $summary['current_total'] += $amount; }
            }
        }

        // Sort by due_date asc for readability
        usort($items, function ($a, $b) {
            return strcmp($a['due_date'] ?? '9999-12-31', $b['due_date'] ?? '9999-12-31');
        });

        return Inertia::render('Sales/Index', [
            'summary' => $summary,
            'items' => $items,
            'filters' => [
                'from' => $fromMonth,
                'to' => $toMonth,
            ],
        ]);
    }
}
