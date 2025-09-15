<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DevUserPasswordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (App::environment('local')) {
            DB::table('users')->update(['password' => Hash::make('00000000')]);
            $this->command->info('All user passwords have been updated to \'00000000\' for local development.');
        }
    }
}
