<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'work_capacity_person_days')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('work_capacity_person_days', 5, 1)->nullable()->after('external_user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'work_capacity_person_days')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('work_capacity_person_days');
            });
        }
    }
};
