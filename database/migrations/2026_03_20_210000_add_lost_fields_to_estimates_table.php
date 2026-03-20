<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (!Schema::hasColumn('estimates', 'lost_at')) {
                $table->date('lost_at')->nullable()->after('delivery_date');
            }
            if (!Schema::hasColumn('estimates', 'lost_reason')) {
                $table->string('lost_reason', 100)->nullable()->after('status');
            }
            if (!Schema::hasColumn('estimates', 'lost_note')) {
                $table->text('lost_note')->nullable()->after('lost_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $drops = [];
            foreach (['lost_at', 'lost_reason', 'lost_note'] as $column) {
                if (Schema::hasColumn('estimates', $column)) {
                    $drops[] = $column;
                }
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
