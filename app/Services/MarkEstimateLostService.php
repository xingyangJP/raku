<?php

namespace App\Services;

use App\Models\Estimate;
use Illuminate\Support\Carbon;

class MarkEstimateLostService
{
    public function execute(Estimate $estimate, array $payload): Estimate
    {
        $lostAt = !empty($payload['lost_at'])
            ? Carbon::parse($payload['lost_at'])->startOfDay()
            : now()->startOfDay();

        $estimate->fill([
            'status' => 'lost',
            'lost_at' => $lostAt->toDateString(),
            'lost_reason' => $payload['lost_reason'],
            'lost_note' => $payload['lost_note'] ?? null,
            'follow_up_due_date' => null,
            'overdue_decision_note' => $payload['lost_note'] ?? null,
            'overdue_prompted_at' => now(),
            'is_order_confirmed' => false,
        ]);

        if (property_exists($estimate, 'approval_started') || isset($estimate->approval_started)) {
            $estimate->approval_started = false;
        }

        $estimate->save();

        return $estimate;
    }
}
