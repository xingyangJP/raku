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
        if (!Schema::hasColumn('estimates', 'delivery_date')) {
            Schema::table('estimates', function (Blueprint $table) {
                $table->date('delivery_date')->nullable()->after('due_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('estimates', 'delivery_date')) {
            Schema::table('estimates', function (Blueprint $table) {
                $table->dropColumn('delivery_date');
            });
        }
    }
};
