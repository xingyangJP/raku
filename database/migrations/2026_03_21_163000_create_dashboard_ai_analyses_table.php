<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dashboard_ai_analyses')) {
            return;
        }

        Schema::create('dashboard_ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->date('analysis_date');
            $table->unsignedSmallInteger('target_year');
            $table->unsignedTinyInteger('target_month');
            $table->string('section_key', 32)->default('overall');
            $table->string('status', 32)->default('completed');
            $table->string('model', 64)->nullable();
            $table->json('analysis_items')->nullable();
            $table->longText('prompt_payload')->nullable();
            $table->longText('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['analysis_date', 'target_year', 'target_month', 'section_key'], 'dashboard_ai_unique_daily_section');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_ai_analyses');
    }
};
