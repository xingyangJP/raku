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
        Schema::create('billings', function (Blueprint $table) {
            $table->string('id')->primary(); // Money Forward ID
            $table->string('pdf_url')->nullable();
            $table->string('operator_id')->nullable();
            $table->string('department_id')->nullable();
            $table->string('member_id')->nullable();
            $table->string('member_name')->nullable();
            $table->string('partner_id')->nullable();
            $table->string('partner_name')->nullable();
            $table->string('office_id')->nullable();
            $table->string('office_name')->nullable();
            $table->string('office_detail')->nullable();
            $table->string('title')->nullable();
            $table->text('memo')->nullable();
            $table->string('payment_condition')->nullable();
            $table->date('billing_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('sales_date')->nullable();
            $table->string('billing_number')->unique()->nullable();
            $table->text('note')->nullable();
            $table->string('document_name')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('email_status')->nullable();
            $table->string('posting_status')->nullable();
            $table->boolean('is_downloaded')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->decimal('deduct_price', 15, 2)->nullable();
            $table->json('tag_names')->nullable();
            $table->decimal('excise_price', 15, 2)->nullable();
            $table->decimal('excise_price_of_untaxable', 15, 2)->nullable();
            $table->decimal('excise_price_of_non_taxable', 15, 2)->nullable();
            $table->decimal('excise_price_of_tax_exemption', 15, 2)->nullable();
            $table->decimal('excise_price_of_five_percent', 15, 2)->nullable();
            $table->decimal('excise_price_of_eight_percent', 15, 2)->nullable();
            $table->decimal('excise_price_of_eight_percent_as_reduced_tax_rate', 15, 2)->nullable();
            $table->decimal('excise_price_of_ten_percent', 15, 2)->nullable();
            $table->decimal('subtotal_price', 15, 2)->nullable();
            $table->decimal('subtotal_of_untaxable_excise', 15, 2)->nullable();
            $table->decimal('subtotal_of_non_taxable_excise', 15, 2)->nullable();
            $table->decimal('subtotal_of_tax_exemption_excise', 15, 2)->nullable();
            $table->decimal('subtotal_of_five_percent_excise', 15, 2)->nullable();
            $table->decimal('subtotal_of_eight_percent_excise', 15, 2)->nullable();
            $table->decimal('subtotal_of_eight_percent_as_reduced_tax_rate_excise', 15, 2)->nullable();
            $table->decimal('subtotal_of_ten_percent_excise', 15, 2)->nullable();
            $table->decimal('subtotal_with_tax_of_untaxable_excise', 15, 2)->nullable();
            $table->decimal('subtotal_with_tax_of_non_taxable_excise', 15, 2)->nullable();
            $table->decimal('subtotal_with_tax_of_tax_exemption_excise', 15, 2)->nullable();
            $table->decimal('subtotal_with_tax_of_five_percent_excise', 15, 2)->nullable();
            $table->decimal('subtotal_with_tax_of_eight_percent_excise', 15, 2)->nullable();
            $table->decimal('subtotal_with_tax_of_eight_percent_as_reduced_tax_rate_excise', 15, 2)->nullable();
            $table->decimal('subtotal_with_tax_of_ten_percent_excise', 15, 2)->nullable();
            $table->decimal('total_price', 15, 2)->nullable();
            $table->string('registration_code')->nullable();
            $table->boolean('use_invoice_template')->default(false);
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billings');
    }
};
