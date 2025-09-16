<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LocalInvoice extends Model
{
    protected $fillable = [
        'estimate_id', 'customer_name', 'client_id', 'department_id', 'title',
        'billing_number', 'billing_date', 'due_date', 'sales_date', 'notes', 'items',
        'total_amount', 'tax_amount', 'staff_id', 'staff_name', 'status',
        'mf_billing_id', 'mf_pdf_url',
    ];

    protected $casts = [
        'items' => 'array',
        'billing_date' => 'date',
        'due_date' => 'date',
        'sales_date' => 'date',
    ];

    public static function generateReadableBillingNumber($staffId, $clientId): string
    {
        $date = now()->format('ymd');
        $staff = $staffId ?: 'X';
        $client = 'X';
        if (!empty($clientId)) {
            $client = (string)$clientId;
            if (Schema::hasTable('partners')) {
                try {
                    $code = DB::table('partners')->where('mf_partner_id', (string)$clientId)->value('code');
                    if (!empty($code)) { $client = $code; }
                } catch (\Throwable $e) {}
            }
            if (strlen($client) > 12 && strpos($client, 'CRM-') !== 0) {
                $client = substr($client, 0, 6);
            }
        }
        $prefix = "INV-$staff-$client-$date-";
        $latest = null;
        if (Schema::hasTable('local_invoices')) {
            $latest = self::where('billing_number', 'like', $prefix . '%')
                ->orderBy('billing_number', 'desc')
                ->first();
        }
        $seq = 1;
        if ($latest) {
            $tail = substr($latest->billing_number, strlen($prefix));
            $num = (int) $tail; $seq = $num + 1;
        }
        return $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
    }
}
