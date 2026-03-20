<?php

namespace App\Services;

use App\Models\Estimate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class QuoteOverdueFollowUpService
{
    public function findPromptCandidate(Collection $estimates, string $timezone = 'Asia/Tokyo'): ?Estimate
    {
        $today = Carbon::now($timezone)->startOfDay();

        return $estimates
            ->filter(function (Estimate $estimate) use ($today) {
                if ($estimate->status === 'lost' || $estimate->is_order_confirmed) {
                    return false;
                }

                $deadline = $this->resolveFollowUpDeadline($estimate);
                if (!$deadline || !$deadline->lt($today)) {
                    return false;
                }

                if (!$estimate->overdue_prompted_at) {
                    return true;
                }

                return Carbon::parse($estimate->overdue_prompted_at)->lt($today);
            })
            ->sortBy([
                fn (Estimate $estimate) => $this->resolveFollowUpDeadline($estimate)?->timestamp ?? PHP_INT_MAX,
                fn (Estimate $estimate) => $estimate->id,
            ])
            ->first();
    }

    public function acknowledgePrompt(Estimate $estimate): Estimate
    {
        $estimate->overdue_prompted_at = now();
        $estimate->save();

        return $estimate;
    }

    public function extendFollowUp(Estimate $estimate, array $payload): Estimate
    {
        $estimate->follow_up_due_date = Carbon::parse($payload['follow_up_due_date'])->startOfDay()->toDateString();
        $estimate->overdue_decision_note = $payload['overdue_decision_note'] ?? null;
        $estimate->overdue_prompted_at = now();
        $estimate->save();

        return $estimate;
    }

    public function resolveFollowUpDeadline(Estimate $estimate): ?Carbon
    {
        $value = $estimate->follow_up_due_date ?? $estimate->due_date ?? null;
        if (!$value) {
            return null;
        }

        return Carbon::parse($value)->startOfDay();
    }
}
