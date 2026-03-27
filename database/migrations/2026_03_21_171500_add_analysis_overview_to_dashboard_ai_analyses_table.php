<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dashboard_ai_analyses') || Schema::hasColumn('dashboard_ai_analyses', 'analysis_overview')) {
            return;
        }

        Schema::table('dashboard_ai_analyses', function (Blueprint $table) {
            $table->json('analysis_overview')->nullable()->after('analysis_items');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('dashboard_ai_analyses') || !Schema::hasColumn('dashboard_ai_analyses', 'analysis_overview')) {
            return;
        }

        Schema::table('dashboard_ai_analyses', function (Blueprint $table) {
            $table->dropColumn('analysis_overview');
        });
    }
};
