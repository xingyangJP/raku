<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_is_displayed_when_partner_auto_sync_is_skipped(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['mf_skip_partner_auto_sync' => true])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('Dashboard')
                ->has('dashboardMetrics')
                ->has('partnerSyncMeta')
                ->has('salesRanking');
        });
    }

    public function test_dashboard_partner_sync_times_are_rendered_in_japan_time(): void
    {
        $user = User::factory()->create();

        Cache::forever('dashboard:partner-sync-meta', [
            'last_synced_at' => '2026-03-20T06:17:00+00:00',
            'cooldown_until' => '2026-03-20T09:17:00+00:00',
            'count' => 120,
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['mf_skip_partner_auto_sync' => true])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('Dashboard')
                ->where('partnerSyncMeta.last_synced_at_label', '2026年3月20日 15:17')
                ->where('partnerSyncMeta.next_auto_sync_available_at_label', '18:17以降');
        });
    }

    public function test_dashboard_can_switch_target_year_and_month(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['mf_skip_partner_auto_sync' => true])
            ->get(route('dashboard', ['year' => 2025, 'month' => 3]));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page): void {
            $page->component('Dashboard')
                ->where('dashboardMetrics.filters.selected_year', 2025)
                ->where('dashboardMetrics.filters.selected_month', 3)
                ->where('dashboardMetrics.periods.current.label', '2025年3月');
        });
    }
}
