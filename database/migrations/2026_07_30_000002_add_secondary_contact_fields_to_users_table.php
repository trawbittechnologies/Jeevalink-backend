<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'secondary_contact_name')) {
                $table->string('secondary_contact_name')->nullable()->after('mobile');
            }
            if (!Schema::hasColumn('users', 'secondary_phone')) {
                $table->string('secondary_phone', 20)->nullable()->after('secondary_contact_name');
            }
            if (!Schema::hasColumn('users', 'whatsapp_number')) {
                $table->string('whatsapp_number', 20)->nullable()->after('secondary_phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('users', 'secondary_contact_name')) {
                $columnsToDrop[] = 'secondary_contact_name';
            }
            if (Schema::hasColumn('users', 'secondary_phone')) {
                $columnsToDrop[] = 'secondary_phone';
            }
            if (Schema::hasColumn('users', 'whatsapp_number')) {
                $columnsToDrop[] = 'whatsapp_number';
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
