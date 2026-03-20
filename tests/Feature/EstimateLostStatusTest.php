<?php

namespace Tests\Feature;

use App\Models\Estimate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EstimateLostStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_can_be_marked_as_lost(): void
    {
        $user = User::factory()->create();
        $estimate = Estimate::factory()->create([
            'status' => 'pending',
            'is_order_confirmed' => false,
        ]);

        $response = $this->actingAs($user)->from(route('quotes.index'))->patch(route('estimates.markLost', $estimate), [
            'lost_reason' => '価格',
            'lost_note' => '価格競争で見送り',
            'lost_at' => '2026-03-20',
        ]);

        $response->assertRedirect(route('quotes.index'));

        $estimate->refresh();
        $this->assertSame('lost', $estimate->status);
        $this->assertSame('価格', $estimate->lost_reason);
        $this->assertSame('価格競争で見送り', $estimate->lost_note);
        $this->assertSame('2026-03-20', $estimate->lost_at?->toDateString());
        $this->assertFalse((bool) $estimate->is_order_confirmed);
    }

    public function test_quotes_page_can_filter_lost_status(): void
    {
        $user = User::factory()->create();
        Cache::forever('mf_quotes_last_sync_at_user_' . $user->id, now()->toIso8601String());

        Estimate::factory()->create([
            'status' => 'lost',
            'lost_reason' => '競合',
            'lost_at' => '2026-03-20',
            'title' => '失注案件',
        ]);

        Estimate::factory()->create([
            'status' => 'pending',
            'title' => '進行案件',
        ]);

        $response = $this->actingAs($user)->get(route('quotes.index', ['status' => 'lost']));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('Quotes/Index')
                ->has('estimates', 1)
                ->where('estimates.0.status', 'lost')
                ->where('estimates.0.title', '失注案件');
        });
    }

    public function test_quotes_page_provides_overdue_follow_up_prompt_for_unhandled_estimate(): void
    {
        $user = User::factory()->create();
        Cache::forever('mf_quotes_last_sync_at_user_' . $user->id, now()->toIso8601String());

        Estimate::factory()->create([
            'status' => 'pending',
            'customer_name' => '延長確認株式会社',
            'title' => '期限切れ案件',
            'due_date' => now()->subDay()->toDateString(),
            'is_order_confirmed' => false,
            'overdue_prompted_at' => null,
        ]);

        $response = $this->actingAs($user)->get(route('quotes.index'));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('Quotes/Index')
                ->where('overdueFollowUpPrompt.title', '期限切れ案件')
                ->where('overdueFollowUpPrompt.customer_name', '延長確認株式会社');
        });
    }

    public function test_quote_follow_up_due_date_can_be_extended(): void
    {
        $user = User::factory()->create();
        $estimate = Estimate::factory()->create([
            'status' => 'pending',
            'due_date' => now()->subDay()->toDateString(),
            'is_order_confirmed' => false,
        ]);

        $response = $this->actingAs($user)->from(route('quotes.index'))->patch(route('estimates.extendOverdueFollowUp', $estimate), [
            'follow_up_due_date' => now()->addDays(10)->toDateString(),
            'overdue_decision_note' => '来週再提案予定',
        ]);

        $response->assertRedirect(route('quotes.index'));

        $estimate->refresh();
        $this->assertSame(now()->addDays(10)->toDateString(), $estimate->follow_up_due_date?->toDateString());
        $this->assertSame('来週再提案予定', $estimate->overdue_decision_note);
        $this->assertNotNull($estimate->overdue_prompted_at);
    }
}
