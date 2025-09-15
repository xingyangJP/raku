<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('sku')->constrained('categories')->nullOnDelete();
            }
            if (!Schema::hasColumn('products', 'seq')) {
                $table->unsignedInteger('seq')->nullable()->after('category_id');
            }
        });

        // Add composite unique for (category_id, seq)
        try {
            Schema::table('products', function (Blueprint $table) {
                $table->unique(['category_id', 'seq'], 'products_category_id_seq_unique');
            });
        } catch (\Throwable $e) {
            // Index might already exist; ignore
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop unique if exists
            if (Schema::hasColumn('products', 'seq') && Schema::hasColumn('products', 'category_id')) {
                $table->dropUnique('products_category_id_seq_unique');
            }
            if (Schema::hasColumn('products', 'seq')) {
                $table->dropColumn('seq');
            }
            if (Schema::hasColumn('products', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }
        });
    }
};
