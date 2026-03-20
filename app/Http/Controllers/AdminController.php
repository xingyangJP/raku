<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
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
                'person_days_per_person_month' => $personDaysPerMonth,
                'person_hours_per_person_day' => $personHoursPerDay,
                'monthly_capacity_person_days' => $monthlyCapacity,
                'monthly_capacity_person_hours' => $monthlyCapacity * $personHoursPerDay,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeManager();

        $validated = $request->validate([
            'operational_staff_count' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $setting = CompanySetting::current();
        $setting->update([
            'operational_staff_count' => (int) $validated['operational_staff_count'],
        ]);

        return back()->with('success', '工数基準の人数設定を更新しました。');
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
