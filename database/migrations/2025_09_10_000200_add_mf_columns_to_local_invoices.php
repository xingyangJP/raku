<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('local_invoices')) {
            Schema::table('local_invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('local_invoices', 'mf_billing_id')) {
                    $table->string('mf_billing_id')->nullable()->after('status');
                }
                if (!Schema::hasColumn('local_invoices', 'mf_pdf_url')) {
                    $table->string('mf_pdf_url')->nullable()->after('mf_billing_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('local_invoices')) {
            Schema::table('local_invoices', function (Blueprint $table) {
                if (Schema::hasColumn('local_invoices', 'mf_billing_id')) {
                    $table->dropColumn('mf_billing_id');
                }
                if (Schema::hasColumn('local_invoices', 'mf_pdf_url')) {
                    $table->dropColumn('mf_pdf_url');
                }
            });
        }
    }
};

