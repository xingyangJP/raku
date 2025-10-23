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
            // SQLite on the deployment server cannot alter columns via change(),
            // so add a dedicated unique index instead.
            $table->unique('estimate_number', 'estimates_estimate_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropUnique('estimates_estimate_number_unique');
        });
    }
};
