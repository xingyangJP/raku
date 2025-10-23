<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = DB::connection()->getDriverName();
        $hasColumn = false;

        if ($connection === 'sqlite') {
            $columns = DB::select(DB::raw("PRAGMA table_info('estimates')"));
            $hasColumn = collect($columns)->contains(fn ($column) => $column->name === 'staff_id');
        } else {
            $hasColumn = Schema::hasColumn('estimates', 'staff_id');
        }

        if ($hasColumn) {
            return;
        }

        Schema::table('estimates', function (Blueprint $table) use ($connection) {
            if ($connection === 'sqlite') {
                $table->unsignedBigInteger('staff_id')->nullable();
            } else {
                $table->foreignId('staff_id')->nullable()->constrained('users');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection()->getDriverName();

        Schema::table('estimates', function (Blueprint $table) use ($connection) {
            if ($connection !== 'sqlite') {
                try { $table->dropForeign(['staff_id']); } catch (\Throwable $e) { /* ignore */ }
            }

            try { $table->dropColumn('staff_id'); } catch (\Throwable $e) { /* ignore */ }
        });
    }
};
