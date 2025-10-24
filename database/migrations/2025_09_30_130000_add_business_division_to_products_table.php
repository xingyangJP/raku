<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'business_division')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('business_division', 50)
                    ->default('fifth_business')
                    ->after('tax_category');
                $table->index('business_division');
            });

            DB::table('products')->update(['business_division' => 'fifth_business']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'business_division')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['business_division']);
                $table->dropColumn('business_division');
            });
        }
    }
};
