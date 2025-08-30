<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            // 会社情報
            $table->string('company_name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('seal_path')->nullable();
            // 会計年度・締め
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(4); // 1-12
            $table->unsignedTinyInteger('monthly_close_day')->default(31); // 1-31（31=月末）
            $table->string('post_close_lock_policy')->default('soft'); // soft/hard/none
            // 税・通貨・丸め
            $table->decimal('default_tax_rate', 5, 2)->default(10.00);
            $table->string('tax_category_default')->default('standard'); // standard/reduced/exempt
            $table->string('calc_order')->default('line_then_tax'); // line_then_tax / doc_then_tax
            $table->string('rounding_subtotal')->default('round'); // round/ceil/floor
            $table->string('rounding_tax')->default('round');
            $table->string('rounding_total')->default('round');
            $table->unsignedTinyInteger('unit_price_precision')->default(0); // 単価小数桁
            $table->string('currency')->default('JPY');
            // 番号採番・伝票ルール
            $table->string('estimate_number_format')->default('EST-{staff}-{client}-{ydm}-{seq3}');
            $table->string('draft_estimate_number_format')->default('EST-D-{staff}-{client}-{ydm}-{seq3}');
            $table->string('sequence_reset_rule')->default('daily'); // daily/monthly/yearly/never
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};

