<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!Schema::hasTable('categories')) {
            $this->command?->warn('Skipping CategorySeeder: categories table not found.');
            return;
        }

        $initial = [
            'コンサル', '開発', '設計', '管理', 'ハードウェア', 'サプライ', 'ライセンス',
        ];

        foreach ($initial as $name) {
            $exists = DB::table('categories')->where('name', $name)->exists();
            if ($exists) continue;

            $this->insertWithNextCode($name);
        }
    }

    private function insertWithNextCode(string $name): void
    {
        $attempts = 0;
        $maxAttempts = 5;

        while ($attempts < $maxAttempts) {
            $attempts++;
            try {
                DB::transaction(function () use ($name) {
                    // lock and get current max code
                    $last = DB::table('categories')
                        ->lockForUpdate()
                        ->orderByDesc('code')
                        ->value('code');

                    $next = $this->nextCode($last);

                    DB::table('categories')->insert([
                        'name' => $name,
                        'code' => $next,
                        'last_item_seq' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }, 3);
                return; // success
            } catch (\Throwable $e) {
                // Likely unique conflict under concurrency; backoff and retry
                usleep(100000 * $attempts); // 100ms, 200ms, ...
            }
        }
        $this->command?->warn("CategorySeeder: failed to insert '$name' after retries.");
    }

    private function nextCode(?string $current): string
    {
        if (!$current) return 'A';
        $letters = str_split(strtoupper($current));
        for ($i = count($letters) - 1; $i >= 0; $i--) {
            if ($letters[$i] !== 'Z') {
                $letters[$i] = chr(ord($letters[$i]) + 1);
                return implode('', $letters);
            }
            $letters[$i] = 'A';
        }
        array_unshift($letters, 'A');
        return implode('', $letters);
    }
}

