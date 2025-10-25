<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('estimates', 'staff_id')) {
            return;
        }

        Schema::table('estimates', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('estimates', 'staff_id')) {
            return;
        }

        Schema::table('estimates', function (Blueprint $table) {
            try {
                $table->dropForeign(['staff_id']);
            } catch (\Throwable $e) {
                // ignore missing FK
            }

            try {
                $table->dropColumn('staff_id');
            } catch (\Throwable $e) {
                // ignore already removed column
            }
        });
    }
};
