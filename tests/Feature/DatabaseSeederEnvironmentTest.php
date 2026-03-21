<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DashboardDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ItemSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class DatabaseSeederEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_calls_dashboard_demo_seeder_only_in_development(): void
    {
        $this->app['env'] = 'development';

        User::query()->create([
            'name' => 'Seeder Test',
            'email' => 'seeder-test@example.com',
            'password' => Hash::make('secret'),
        ]);

        $seeder = Mockery::mock(DatabaseSeeder::class)->makePartial();
        $seeder->shouldReceive('call')->once()->with(UserSeeder::class);
        $seeder->shouldReceive('call')->once()->with(CategorySeeder::class);
        $seeder->shouldReceive('call')->once()->with(ItemSeeder::class);
        $seeder->shouldReceive('call')->once()->with(DashboardDemoSeeder::class);

        $seeder->run();
    }

    public function test_database_seeder_does_not_call_dashboard_demo_seeder_in_production(): void
    {
        $this->app['env'] = 'production';

        User::query()->create([
            'name' => 'Seeder Test',
            'email' => 'seeder-test@example.com',
            'password' => Hash::make('secret'),
        ]);

        $seeder = Mockery::mock(DatabaseSeeder::class)->makePartial();
        $seeder->shouldReceive('call')->once()->with(UserSeeder::class);
        $seeder->shouldReceive('call')->once()->with(CategorySeeder::class);
        $seeder->shouldReceive('call')->once()->with(ItemSeeder::class);
        $seeder->shouldNotReceive('call')->with(DashboardDemoSeeder::class);

        $seeder->run();
    }
}
