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
        Schema::table('products', function (Blueprint $table) {
            // Add Money Forward specific fields
            $table->string('mf_id')->nullable()->unique()->after('id');
            $table->decimal('quantity', 15, 2)->nullable()->after('price');
            $table->boolean('is_deduct_withholding_tax')->nullable()->after('tax_category');
            $table->timestamp('mf_updated_at')->nullable()->after('updated_at');

            // Change price to decimal to support floats from MF API
            $table->decimal('price', 15, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('mf_id');
            $table->dropColumn('quantity');
            $table->dropColumn('is_deduct_withholding_tax');
            $table->dropColumn('mf_updated_at');

            $table->integer('price')->default(0)->change();
        });
    }
};