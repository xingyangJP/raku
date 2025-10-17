<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            if (!Schema::hasColumn('billings', 'mf_deleted_at')) {
                $table->timestamp('mf_deleted_at')->nullable()->after('config');
            }
            if (!Schema::hasColumn('billings', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('estimates', function (Blueprint $table) {
            if (!Schema::hasColumn('estimates', 'mf_deleted_at')) {
                $afterColumn = 'mf_invoice_pdf_url';
                if (!Schema::hasColumn('estimates', $afterColumn) && Schema::hasColumn('estimates', 'mf_invoice_id')) {
                    $afterColumn = 'mf_invoice_id';
                }
                if (!Schema::hasColumn('estimates', $afterColumn)) {
                    $afterColumn = null;
                }

                if ($afterColumn) {
                    $table->timestamp('mf_deleted_at')->nullable()->after($afterColumn);
                } else {
                    $table->timestamp('mf_deleted_at')->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            if (Schema::hasColumn('billings', 'mf_deleted_at')) {
                $table->dropColumn('mf_deleted_at');
            }
            if (Schema::hasColumn('billings', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('estimates', function (Blueprint $table) {
            if (Schema::hasColumn('estimates', 'mf_deleted_at')) {
                $table->dropColumn('mf_deleted_at');
            }
        });
    }
};
