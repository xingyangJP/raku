<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LocalInvoiceResetSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('local_invoices')) {
            $this->command?->warn('Skipping LocalInvoiceResetSeeder: local_invoices table not found.');
            return;
        }

        DB::table('local_invoices')->truncate();
        $this->command?->info('local_invoices テーブルを初期化しました。');
    }
}
