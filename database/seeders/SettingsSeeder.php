<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('company_settings')->truncate();
        DB::table('company_settings')->insert([
            'company_name' => '株式会社テックソリューション',
            'address' => '東京都千代田区1-1-1',
            'phone' => '03-1234-5678',
            'email' => 'info@example.com',
            'logo_path' => null,
            'seal_path' => null,
            'fiscal_year_start_month' => 4,
            'monthly_close_day' => 31,
            'post_close_lock_policy' => 'soft',
            'default_tax_rate' => 10.00,
            'tax_category_default' => 'standard',
            'calc_order' => 'line_then_tax',
            'rounding_subtotal' => 'round',
            'rounding_tax' => 'round',
            'rounding_total' => 'round',
            'unit_price_precision' => 0,
            'currency' => 'JPY',
            'estimate_number_format' => 'EST-{staff}-{client}-{ydm}-{seq3}',
            'draft_estimate_number_format' => 'EST-D-{staff}-{client}-{ydm}-{seq3}',
            'sequence_reset_rule' => 'daily',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('approval_settings')->truncate();
        DB::table('approval_settings')->insert([
            'default_flow' => json_encode([
                ['id' => 2, 'name' => '一次承認者'],
                ['id' => 7, 'name' => '二次承認者'],
            ]),
            'threshold_rules' => json_encode([
                ['min' => 1000000, 'flow' => [ ['id'=>9,'name'=>'部長'], ['id'=>10,'name'=>'役員'] ]]
            ]),
            'remind_after_days' => 3,
            'remind_interval_days' => 3,
            'allow_delegate' => true,
            'allow_skip' => false,
            'admin_override' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (DB::table('users')->where('id', 1)->exists()) {
            DB::table('settings_permissions')->updateOrInsert(
                ['user_id' => 1],
                ['role' => 'admin', 'can_access' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        // Output Japanese summary
        $companyCount = DB::table('company_settings')->count();
        $approvalCount = DB::table('approval_settings')->count();
        $permCount = DB::table('settings_permissions')->count();
        $this->command?->info(sprintf('会社設定: %d件 / 承認設定: %d件 / 設定権限: %d件', $companyCount, $approvalCount, $permCount));
    }
}
