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
        if (!Schema::hasTable('campaigns')) {
            Schema::create('campaigns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('author_name')->nullable();
                $table->string('author_role')->nullable();
                $table->string('title');
                $table->string('category')->default('blood_donation');
                $table->text('description');
                $table->string('venue')->nullable();
                $table->string('event_date')->nullable();
                $table->string('event_time')->nullable();
                $table->string('organizer_name')->nullable();
                $table->string('contact_phone')->nullable();
                $table->longText('image_url')->nullable();
                $table->string('district')->nullable();
                $table->string('block')->nullable();
                $table->integer('likes_count')->default(0);
                $table->integer('shares_count')->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
