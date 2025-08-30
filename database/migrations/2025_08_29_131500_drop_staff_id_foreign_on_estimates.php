<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            try { $table->dropForeign(['staff_id']); } catch (\Throwable $e) { /* ignore if not exists */ }
        });
    }

    public function down(): void
    {
        // No-op: Do not re-add foreign key to external ID column
    }
};

