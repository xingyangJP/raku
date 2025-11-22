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
        Schema::create('maintenance_fee_snapshot_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_fee_snapshot_id')->constrained()->onDelete('cascade');
            $table->string('customer_name');
            $table->decimal('maintenance_fee', 15, 2)->default(0);
            $table->string('status', 100)->nullable();
            $table->string('support_type', 255)->nullable();
            $table->timestamps();
            $table->index(['maintenance_fee_snapshot_id', 'customer_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_fee_snapshot_items');
    }
};
