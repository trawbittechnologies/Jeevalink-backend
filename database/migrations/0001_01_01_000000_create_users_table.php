<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // PostgreSQL: schema.sql (run via import_schema_sql migration) already
        // creates the users table with the correct Jeevalink columns.
        // We only create it here for SQLite (local dev / testing).
        if ($driver !== 'pgsql') {
            if (!Schema::hasTable('users')) {
                Schema::create('users', function (Blueprint $table) {
                    $table->id();
                    $table->string('full_name');
                    $table->string('email')->unique();
                    $table->string('mobile', 20)->nullable();
                    $table->string('secondary_contact_name')->nullable();
                    $table->string('secondary_phone', 20)->nullable();
                    $table->string('whatsapp_number', 20)->nullable();
                    $table->string('password_hash')->nullable();
                    $table->string('password')->nullable();  // Laravel auth compat
                    $table->string('role')->default('user');
                    $table->string('blood_group', 5)->default('N/A');
                    $table->string('city', 100)->nullable();
                    $table->string('district', 100)->nullable();
                    $table->string('organization_name')->nullable();
                    $table->string('volunteer_type')->nullable();
                    $table->string('pin_code', 10)->nullable();
                    $table->string('pincode', 10)->nullable();
                    $table->text('address')->nullable();
                    $table->text('remarks')->nullable();
                    $table->text('full_address')->nullable();
                    $table->integer('weight')->nullable();
                    $table->date('date_of_birth')->nullable();
                    $table->date('dob')->nullable();
                    $table->date('last_donated_date')->nullable();
                    $table->text('profile_picture')->nullable();
                    $table->text('id_proof_front')->nullable();
                    $table->text('id_proof_back')->nullable();
                    $table->string('sex', 20)->nullable();
                    $table->boolean('available_for_donation')->default(true);
                    $table->integer('reward_points')->default(100);
                    $table->integer('lives_saved')->default(0);
                    $table->integer('total_donations')->default(0);
                    $table->string('status')->default('Active');
                    $table->string('eligibility_status', 50)->default('Pending Check');
                    $table->timestamp('eligibility_checked_at')->nullable();
                    $table->boolean('is_verified')->default(false);
                    $table->text('expo_push_token')->nullable();
                    $table->text('fcm_token')->nullable();
                    $table->decimal('latitude', 10, 8)->nullable();
                    $table->decimal('longitude', 11, 8)->nullable();
                    $table->boolean('notification_enabled')->default(true);
                    $table->rememberToken();
                    $table->timestamps();
                });
            }
        }

        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
