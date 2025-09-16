<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('local_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('local_invoices', 'sales_date')) {
                $table->date('sales_date')->nullable()->after('due_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('local_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('local_invoices', 'sales_date')) {
                $table->dropColumn('sales_date');
            }
        });
    }
};

