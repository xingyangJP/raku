<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (!Schema::hasColumn('estimates', 'follow_up_due_date')) {
                $table->date('follow_up_due_date')->nullable()->after('due_date');
            }
            if (!Schema::hasColumn('estimates', 'overdue_prompted_at')) {
                $table->dateTime('overdue_prompted_at')->nullable()->after('follow_up_due_date');
            }
            if (!Schema::hasColumn('estimates', 'overdue_decision_note')) {
                $table->text('overdue_decision_note')->nullable()->after('overdue_prompted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $drops = [];
            foreach (['follow_up_due_date', 'overdue_prompted_at', 'overdue_decision_note'] as $column) {
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
