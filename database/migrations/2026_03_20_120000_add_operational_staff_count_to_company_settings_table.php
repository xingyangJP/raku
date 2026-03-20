<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('company_settings', 'operational_staff_count')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->unsignedInteger('operational_staff_count')->default(4)->after('sequence_reset_rule');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('company_settings', 'operational_staff_count')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->dropColumn('operational_staff_count');
            });
        }
    }
};
