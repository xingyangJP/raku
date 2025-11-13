<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (!Schema::hasColumn('estimates', 'structured_requirements')) {
                $table->json('structured_requirements')->nullable()->after('requirement_summary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (Schema::hasColumn('estimates', 'structured_requirements')) {
                $table->dropColumn('structured_requirements');
            }
        });
    }
};
