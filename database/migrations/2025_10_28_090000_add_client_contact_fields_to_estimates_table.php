<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (!Schema::hasColumn('estimates', 'client_contact_name')) {
                $table->string('client_contact_name', 35)->nullable()->after('customer_name');
            }
            if (!Schema::hasColumn('estimates', 'client_contact_title')) {
                $table->string('client_contact_title', 35)->nullable()->after('client_contact_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            if (Schema::hasColumn('estimates', 'client_contact_title')) {
                $table->dropColumn('client_contact_title');
            }
            if (Schema::hasColumn('estimates', 'client_contact_name')) {
                $table->dropColumn('client_contact_name');
            }
        });
    }
};
