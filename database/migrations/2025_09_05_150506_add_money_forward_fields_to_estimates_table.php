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
        Schema::table('estimates', function (Blueprint $table) {
            $table->string('mf_quote_id')->nullable()->after('status');
            $table->string('mf_quote_pdf_url')->nullable()->after('mf_quote_id');
            $table->string('mf_invoice_id')->nullable()->after('mf_quote_pdf_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn(['mf_quote_id', 'mf_quote_pdf_url', 'mf_invoice_id']);
        });
    }
};
