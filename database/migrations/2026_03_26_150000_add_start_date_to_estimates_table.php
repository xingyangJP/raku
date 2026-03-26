<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (!Schema::hasColumn('estimates', 'start_date')) {
                $table->date('start_date')->nullable()->after('due_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (Schema::hasColumn('estimates', 'start_date')) {
                $table->dropColumn('start_date');
            }
        });
    }
};
