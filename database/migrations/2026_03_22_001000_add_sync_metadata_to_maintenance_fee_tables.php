<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_fee_snapshots', function (Blueprint $table) {
            $table->timestamp('last_synced_at')->nullable()->after('source');
        });

        Schema::table('maintenance_fee_snapshot_items', function (Blueprint $table) {
            $table->string('entry_source', 20)->default('api')->after('support_type');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_fee_snapshot_items', function (Blueprint $table) {
            $table->dropColumn('entry_source');
        });

        Schema::table('maintenance_fee_snapshots', function (Blueprint $table) {
            $table->dropColumn('last_synced_at');
        });
    }
};
