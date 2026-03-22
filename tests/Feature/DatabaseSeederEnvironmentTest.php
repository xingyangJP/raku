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

    protected function tearDown(): void
    {
        putenv('DASHBOARD_DEMO_SEED_ENABLED');
        unset($_ENV['DASHBOARD_DEMO_SEED_ENABLED'], $_SERVER['DASHBOARD_DEMO_SEED_ENABLED']);

        parent::tearDown();
    }

    public function test_database_seeder_calls_dashboard_demo_seeder_only_when_development_flag_is_enabled(): void
    {
        $this->app['env'] = 'development';
        putenv('DASHBOARD_DEMO_SEED_ENABLED=true');
        $_ENV['DASHBOARD_DEMO_SEED_ENABLED'] = 'true';
        $_SERVER['DASHBOARD_DEMO_SEED_ENABLED'] = 'true';

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

    public function test_database_seeder_does_not_call_dashboard_demo_seeder_when_flag_is_disabled(): void
    {
        $this->app['env'] = 'development';
        putenv('DASHBOARD_DEMO_SEED_ENABLED=false');
        $_ENV['DASHBOARD_DEMO_SEED_ENABLED'] = 'false';
        $_SERVER['DASHBOARD_DEMO_SEED_ENABLED'] = 'false';

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

    public function test_database_seeder_does_not_call_dashboard_demo_seeder_in_production_even_if_flag_is_enabled(): void
    {
        $this->app['env'] = 'production';
        putenv('DASHBOARD_DEMO_SEED_ENABLED=true');
        $_ENV['DASHBOARD_DEMO_SEED_ENABLED'] = 'true';
        $_SERVER['DASHBOARD_DEMO_SEED_ENABLED'] = 'true';

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
