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
        Schema::create('billing_items', function (Blueprint $table) {
            $table->string('id')->primary(); // Money Forward Item ID
            $table->string('billing_id'); // Foreign key to billings table
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->text('detail')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('quantity', 15, 2)->nullable();
            $table->boolean('is_deduct_withholding_tax')->default(false);
            $table->string('excise')->nullable();
            $table->string('delivery_number')->nullable();
            $table->date('delivery_date')->nullable();
            $table->timestamps();

            $table->foreign('billing_id')->references('id')->on('billings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_items');
    }
};
