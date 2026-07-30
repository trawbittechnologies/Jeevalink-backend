<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates core application tables when using SQLite (local dev).
 * On PostgreSQL (production), these tables are created by the raw schema.sql import.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only run on SQLite – PostgreSQL gets these tables from schema.sql
        if (config('database.default') !== 'sqlite') {
            return;
        }

        // ── Extend users table with all app-required fields ──────────────────
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'mobile')) {
                $table->string('mobile', 20)->nullable()->unique();
            }
            if (!Schema::hasColumn('users', 'password_hash')) {
                $table->string('password_hash')->nullable();
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('user'); // technical_admin|super_admin|block_admin|volunteer|unit_squad|user
            }
            if (!Schema::hasColumn('users', 'blood_group')) {
                $table->string('blood_group', 5)->default('N/A');
            }
            if (!Schema::hasColumn('users', 'city')) {
                $table->string('city', 100)->nullable();
            }
            if (!Schema::hasColumn('users', 'district')) {
                $table->string('district', 100)->nullable();
            }
            if (!Schema::hasColumn('users', 'full_address')) {
                $table->text('full_address')->nullable();
            }
            if (!Schema::hasColumn('users', 'weight')) {
                $table->integer('weight')->nullable();
            }
            if (!Schema::hasColumn('users', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable();
            }
            if (!Schema::hasColumn('users', 'dob')) {
                $table->date('dob')->nullable();
            }
            if (!Schema::hasColumn('users', 'last_donated_date')) {
                $table->date('last_donated_date')->nullable();
            }
            if (!Schema::hasColumn('users', 'profile_picture')) {
                $table->text('profile_picture')->nullable();
            }
            if (!Schema::hasColumn('users', 'id_proof_front')) {
                $table->text('id_proof_front')->nullable();
            }
            if (!Schema::hasColumn('users', 'id_proof_back')) {
                $table->text('id_proof_back')->nullable();
            }
            if (!Schema::hasColumn('users', 'sex')) {
                $table->string('sex', 20)->nullable();
            }
            if (!Schema::hasColumn('users', 'available_for_donation')) {
                $table->boolean('available_for_donation')->default(true);
            }
            if (!Schema::hasColumn('users', 'reward_points')) {
                $table->integer('reward_points')->default(100);
            }
            if (!Schema::hasColumn('users', 'lives_saved')) {
                $table->integer('lives_saved')->default(0);
            }
            if (!Schema::hasColumn('users', 'total_donations')) {
                $table->integer('total_donations')->default(0);
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('Active');
            }
            if (!Schema::hasColumn('users', 'is_verified')) {
                $table->boolean('is_verified')->default(false);
            }
            if (!Schema::hasColumn('users', 'expo_push_token')) {
                $table->text('expo_push_token')->nullable();
            }
            if (!Schema::hasColumn('users', 'full_name')) {
                $table->string('full_name')->nullable();
            }
        });

        // ── blood_requests ────────────────────────────────────────────────────
        if (!Schema::hasTable('blood_requests')) {
            Schema::create('blood_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('requested_by');
                $table->string('patient_name');
                $table->string('blood_group', 5);
                $table->integer('units_required')->default(1);
                $table->string('hospital_name');
                $table->text('hospital_address')->nullable();
                $table->string('city', 100);
                $table->string('district', 100);
                $table->text('location')->nullable();
                $table->string('contact_number', 20);
                $table->string('contact_person_name')->nullable();
                $table->timestamp('required_by_date');
                $table->string('urgency_level')->default('Normal'); // Normal|Urgent|Emergency SOS
                $table->text('additional_notes')->nullable();
                $table->string('status')->default('Pending'); // Pending|Fulfilled
                $table->boolean('verified')->default(false);
                $table->unsignedBigInteger('accepted_by')->nullable();
                $table->unsignedBigInteger('fulfilled_by')->nullable();
                $table->timestamps();

                $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('accepted_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('fulfilled_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        // ── notifications ─────────────────────────────────────────────────────
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('recipient_id');
                $table->string('title');
                $table->text('message');
                $table->string('type'); // SOS|Reward|Match|Fulfilled|Warning
                $table->boolean('is_read')->default(false);
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('recipient_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // ── complaints ────────────────────────────────────────────────────────
        if (!Schema::hasTable('complaints')) {
            Schema::create('complaints', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('reporter_id');
                $table->unsignedBigInteger('target_id');
                $table->text('reason');
                $table->string('status')->default('Pending'); // Pending|Resolved
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('reporter_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('target_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        // ── notification_logs ─────────────────────────────────────────────────
        if (!Schema::hasTable('notification_logs')) {
            Schema::create('notification_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->text('fcm_token')->nullable();
                $table->string('title')->nullable();
                $table->text('body')->nullable();
                $table->json('data')->nullable();
                $table->string('status', 30)->default('pending')->index();
                $table->string('fcm_message_id')->nullable();
                $table->text('error_message')->nullable();
                $table->integer('attempt')->default(1);
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('blood_requests');
    }
};
