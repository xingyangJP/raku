<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (!Schema::hasColumn('estimates', 'mf_invoice_pdf_url')) {
                $position = 'mf_invoice_id';
                if (!Schema::hasColumn('estimates', $position) && Schema::hasColumn('estimates', 'mf_quote_pdf_url')) {
                    $position = 'mf_quote_pdf_url';
                }

                if (Schema::hasColumn('estimates', $position)) {
                    $table->string('mf_invoice_pdf_url')->nullable()->after($position);
                } else {
                    $table->string('mf_invoice_pdf_url')->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (Schema::hasColumn('estimates', 'mf_invoice_pdf_url')) {
                $table->dropColumn('mf_invoice_pdf_url');
            }
        });
    }
};
