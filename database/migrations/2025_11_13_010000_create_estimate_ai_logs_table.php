<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('estimate_ai_logs')) {
            return;
        }

        Schema::create('estimate_ai_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')
                ->nullable()
                ->constrained('estimates')
                ->nullOnDelete();
            $table->string('action', 50);
            $table->text('input_summary')->nullable();
            $table->json('structured_requirements')->nullable();
            $table->longText('prompt_payload')->nullable();
            $table->longText('ai_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_ai_logs');
    }
};
