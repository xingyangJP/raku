<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requirement_chat_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimate_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('requirement_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('requirement_chat_threads')->onDelete('cascade');
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->text('content');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirement_chat_messages');
        Schema::dropIfExists('requirement_chat_threads');
    }
};
