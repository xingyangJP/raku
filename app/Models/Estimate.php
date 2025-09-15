<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Estimate extends Model
{
    protected $fillable = [
        'customer_name',
        'client_id',
        'mf_department_id',
        'title',
        'issue_date',
        'due_date',
        'status',
        'total_amount',
        'tax_amount',
        'notes',
        'items',
        'estimate_number',
        'staff_id',
        'staff_name',
        'approval_flow',
        'approval_started',
        'internal_memo',
        'delivery_location',
        'mf_quote_id',
        'mf_quote_pdf_url',
        'mf_invoice_id',
        'mf_invoice_pdf_url',
    ];

    protected $casts = [
        'items' => 'array',
        'issue_date' => 'date',
        'due_date' => 'date',
        'approval_flow' => 'array',
        'approval_started' => 'boolean',
    ];

    // No local FK relation for staff; staff_id refers to external directory

    public static function generateReadableEstimateNumber($staffId, $clientId, bool $is_draft): string
    {
        // Spec: EST[-D]-{staff}-{client}-{yyddmm}-{seq}
        $date = now()->format('ydm'); // yy dd mm
        $staff = $staffId ?: 'X';

        // Prefer short partner code from DB; fallback to leading 6 of partner_id
        $client = 'X';
        if (!empty($clientId)) {
            $client = (string)$clientId;
            if (Schema::hasTable('partners')) {
                try {
                    $code = \DB::table('partners')->where('mf_partner_id', (string)$clientId)->value('code');
                    if (!empty($code)) { $client = $code; }
                } catch (\Throwable $e) {}
            }
            if (strlen($client) > 12 && strpos($client, 'CRM-') !== 0) {
                $client = substr($client, 0, 6);
            }
        }
        $kind = $is_draft ? 'EST-D' : 'EST';
        $prefix = "$kind-$staff-$client-$date-";

        $latest = self::where('estimate_number', 'like', $prefix . '%')
            ->orderBy('estimate_number', 'desc')
            ->first();

        $seq = 1;
        if ($latest) {
            $tail = substr($latest->estimate_number, strlen($prefix));
            $num = (int) $tail;
            $seq = $num + 1;
        }

        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
