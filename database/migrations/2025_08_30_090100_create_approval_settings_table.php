<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_settings', function (Blueprint $table) {
            $table->id();
            $table->json('default_flow')->nullable();
            $table->json('threshold_rules')->nullable();
            $table->unsignedTinyInteger('remind_after_days')->default(3);
            $table->unsignedTinyInteger('remind_interval_days')->default(3);
            $table->boolean('allow_delegate')->default(true);
            $table->boolean('allow_skip')->default(false);
            $table->boolean('admin_override')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_settings');
    }
};

