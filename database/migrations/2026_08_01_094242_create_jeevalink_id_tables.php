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
        if (!Schema::hasTable('place_codes')) {
            Schema::create('place_codes', function (Blueprint $table) {
                $table->id();
                $table->string('place_name')->index();
                $table->string('place_type', 50);
                $table->string('code', 3)->unique();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('jeevalink_id_sequences')) {
            Schema::create('jeevalink_id_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('role_code', 2);
                $table->string('place_code', 3);
                $table->integer('latest_sequence')->default(9);
                $table->timestamps();
                $table->unique(['role_code', 'place_code']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'jeevalink_id')) {
                $table->string('jeevalink_id', 25)->nullable()->unique()->after('id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jeevalink_id_tables');
    }
};
