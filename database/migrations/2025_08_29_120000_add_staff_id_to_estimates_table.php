<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (!Schema::hasColumn('estimates', 'staff_id')) {
                $table->foreignId('staff_id')->nullable()->constrained('users');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (Schema::hasColumn('estimates', 'staff_id')) {
                // Drop FK if exists; ignore if not present
                try { $table->dropForeign(['staff_id']); } catch (\Throwable $e) { /* ignore */ }
                $table->dropColumn('staff_id');
            }
        });
    }
};
