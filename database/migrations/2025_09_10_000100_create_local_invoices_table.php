<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('local_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('estimate_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('client_id')->nullable(); // MF partner_id
            $table->string('department_id')->nullable(); // MF department_id
            $table->string('title')->nullable();
            $table->string('billing_number')->unique()->nullable();
            $table->date('billing_date')->nullable();
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->json('items')->nullable();
            $table->integer('total_amount')->default(0);
            $table->integer('tax_amount')->default(0);
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->string('staff_name')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_invoices');
    }
};

