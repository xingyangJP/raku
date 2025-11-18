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
        Schema::create('maintenance_fee_snapshots', function (Blueprint $table) {
            $table->id();
            // 対象月（1日固定の日付で保持）
            $table->date('month')->index();
            // 月額保守合計（粗利も同額として扱う）
            $table->decimal('total_fee', 15, 2);
            $table->decimal('total_gross', 15, 2);
            $table->string('source', 50)->nullable(); // 取得元の識別子（api など）
            $table->timestamps();
            $table->unique('month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_fee_snapshots');
    }
};
