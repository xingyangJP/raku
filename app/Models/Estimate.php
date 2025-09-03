<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estimate extends Model
{
    protected $fillable = [
        'customer_name',
        'client_id',
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
        $date = now()->format('ydm');
        $staff = $staffId ?: 'X';
        $client = $clientId ?: 'X';
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