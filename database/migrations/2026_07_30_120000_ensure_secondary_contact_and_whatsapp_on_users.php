<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safety migration: ensures secondary_contact_name, secondary_phone, and
 * whatsapp_number columns exist on the users table. Uses hasColumn guards so
 * it is fully idempotent and safe to run on any environment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'secondary_contact_name')) {
                $table->string('secondary_contact_name')->nullable();
            }
            if (!Schema::hasColumn('users', 'secondary_phone')) {
                $table->string('secondary_phone', 20)->nullable();
            }
            if (!Schema::hasColumn('users', 'whatsapp_number')) {
                $table->string('whatsapp_number', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('users', 'secondary_contact_name')) $drop[] = 'secondary_contact_name';
            if (Schema::hasColumn('users', 'secondary_phone'))         $drop[] = 'secondary_phone';
            if (Schema::hasColumn('users', 'whatsapp_number'))         $drop[] = 'whatsapp_number';
            if (!empty($drop)) $table->dropColumn($drop);
        });
    }
};
