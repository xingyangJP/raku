<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminCapacitySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_page_includes_user_capacity_rows(): void
    {
        $manager = User::factory()->create([
            'id' => 3,
            'name' => '管理者',
        ]);
        $capacityUser = User::factory()->create([
            'name' => '営業A',
            'work_capacity_person_days' => 5,
        ]);

        $response = $this->actingAs($manager)->get(route('admin.index'));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page) use ($capacityUser): void {
            $page->component('Admin/Index')
                ->where('capacityUsers', function ($rows) use ($capacityUser) {
                    return collect($rows)->contains(function ($row) use ($capacityUser) {
                        return (int) ($row['id'] ?? 0) === $capacityUser->id
                            && (float) ($row['resolved_capacity_person_days'] ?? 0) === 5.0;
                    });
                })
                ->where('settings.monthly_capacity_person_days', fn ($value) => (float) $value === 25.0);
        });
    }

    public function test_admin_page_excludes_local_helper_users_from_capacity_rows(): void
    {
        $manager = User::factory()->create([
            'id' => 3,
            'name' => '管理者',
            'work_capacity_person_days' => 20,
        ]);
        User::factory()->create([
            'name' => 'Codex UI Check',
            'email' => 'codex-ui-check@example.com',
            'work_capacity_person_days' => 20,
        ]);
        $businessUser = User::factory()->create([
            'name' => '営業A',
            'work_capacity_person_days' => 5,
        ]);

        $response = $this->actingAs($manager)->get(route('admin.index'));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page) use ($businessUser): void {
            $page->component('Admin/Index')
                ->where('capacityUsers', function ($rows) use ($businessUser) {
                    $collection = collect($rows);

                    return !$collection->contains(fn ($row) => ($row['name'] ?? null) === 'Codex UI Check')
                        && $collection->contains(fn ($row) => (int) ($row['id'] ?? 0) === $businessUser->id);
                })
                ->where('settings.monthly_capacity_person_days', fn ($value) => (float) $value === 25.0)
                ->where('settings.operational_staff_count', fn ($value) => (int) $value === 2);
        });
    }

    public function test_admin_update_saves_user_specific_capacity(): void
    {
        $manager = User::factory()->create([
            'id' => 3,
            'name' => '管理者',
        ]);
        $userA = User::factory()->create([
            'name' => '営業A',
        ]);
        $userB = User::factory()->create([
            'name' => '総務B',
            'work_capacity_person_days' => 4,
        ]);

        $response = $this->actingAs($manager)->post(route('admin.update'), [
            'operational_staff_count' => 10,
            'user_capacities' => [
                ['id' => $manager->id, 'work_capacity_person_days' => 20],
                ['id' => $userA->id, 'work_capacity_person_days' => 6],
                ['id' => $userB->id, 'work_capacity_person_days' => 0],
            ],
        ]);

        $response->assertRedirect();

        $this->assertSame(20.0, (float) $manager->fresh()->work_capacity_person_days);
        $this->assertSame(6.0, (float) $userA->fresh()->work_capacity_person_days);
        $this->assertSame(0.0, (float) $userB->fresh()->work_capacity_person_days);
        $this->assertSame(26.0, (float) CompanySetting::current()->resolveMonthlyCapacityPersonDays());
        $this->assertSame(2, CompanySetting::current()->resolveOperationalStaffCount());
    }
}
