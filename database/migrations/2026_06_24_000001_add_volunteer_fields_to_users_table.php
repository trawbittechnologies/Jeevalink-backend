<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add volunteer-specific fields to users table
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'organization_name')) {
                $table->string('organization_name')->nullable()->after('district');
            }
            if (!Schema::hasColumn('users', 'volunteer_type')) {
                $table->string('volunteer_type')->nullable()->after('organization_name');
            }
            if (!Schema::hasColumn('users', 'secondary_phone')) {
                $table->string('secondary_phone')->nullable()->after('mobile');
            }
            if (!Schema::hasColumn('users', 'pin_code')) {
                $table->string('pin_code', 10)->nullable()->after('city');
            }
            if (!Schema::hasColumn('users', 'address')) {
                $table->text('address')->nullable()->after('pin_code');
            }
            if (!Schema::hasColumn('users', 'remarks')) {
                $table->text('remarks')->nullable()->after('address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['organization_name', 'volunteer_type', 'secondary_phone', 'pin_code', 'address', 'remarks']);
        });
    }
};
