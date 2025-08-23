<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if user already exists
        if (!User::where('email', 'xero_online@me.com')->exists()) {
            User::create([
                'name' => 'Xero Online',
                'email' => 'xero_online@me.com',
                'password' => Hash::make('00000000'),
            ]);
        }
    }
}