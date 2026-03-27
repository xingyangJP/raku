<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function index()
    {
        $this->authorizeManager();

        $setting = CompanySetting::current();
        $personDaysPerMonth = (float) config('app.person_days_per_person_month', 20);
        $personHoursPerDay = (float) config('app.person_hours_per_person_day', 8);
        $staffCount = $setting->resolveOperationalStaffCount();
        $monthlyCapacity = $setting->resolveMonthlyCapacityPersonDays();

        return Inertia::render('Admin/Index', [
            'settings' => [
                'operational_staff_count' => $staffCount,
                'default_capacity_person_days' => $setting->resolveDefaultCapacityPerPersonDays(),
                'person_days_per_person_month' => $personDaysPerMonth,
                'person_hours_per_person_day' => $personHoursPerDay,
                'monthly_capacity_person_days' => $monthlyCapacity,
                'monthly_capacity_person_hours' => $monthlyCapacity * $personHoursPerDay,
            ],
            'capacityUsers' => $setting->resolveUsersCapacityRows()->values()->all(),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeManager();

        $validated = $request->validate([
            'operational_staff_count' => ['required', 'integer', 'min:1', 'max:500'],
            'user_capacities' => ['nullable', 'array'],
            'user_capacities.*.id' => ['required', 'integer', 'exists:users,id'],
            'user_capacities.*.work_capacity_person_days' => ['nullable', 'numeric', 'min:0', 'max:31'],
        ]);

        $setting = CompanySetting::current();
        $setting->update([
            'operational_staff_count' => (int) $validated['operational_staff_count'],
        ]);

        foreach ($validated['user_capacities'] ?? [] as $row) {
            User::query()
                ->whereKey((int) $row['id'])
                ->update([
                    'work_capacity_person_days' => isset($row['work_capacity_person_days']) && $row['work_capacity_person_days'] !== ''
                        ? round((float) $row['work_capacity_person_days'], 1)
                        : null,
                ]);
        }

        return back()->with('success', '工数基準設定を更新しました。');
    }

    private function authorizeManager(): void
    {
        $user = request()->user();
        $allowedIds = [3, 8];

        if (!$user || !in_array($user->id, $allowedIds, true)) {
            abort(403, 'You are not allowed to update company settings.');
        }
    }
}
