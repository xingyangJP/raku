<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (!Schema::hasColumn('estimates', 'xero_project_id')) {
                $table->string('xero_project_id')->nullable()->after('client_id');
            }
            if (!Schema::hasColumn('estimates', 'xero_project_name')) {
                $table->string('xero_project_name')->nullable()->after('xero_project_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (Schema::hasColumn('estimates', 'xero_project_name')) {
                $table->dropColumn('xero_project_name');
            }
            if (Schema::hasColumn('estimates', 'xero_project_id')) {
                $table->dropColumn('xero_project_id');
            }
        });
    }
};

