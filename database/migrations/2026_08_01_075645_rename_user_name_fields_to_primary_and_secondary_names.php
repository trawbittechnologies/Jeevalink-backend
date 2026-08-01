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
            if (Schema::hasColumn('users', 'full_name')) {
                $table->renameColumn('full_name', 'primary_name');
            } elseif (Schema::hasColumn('users', 'name')) {
                $table->renameColumn('name', 'primary_name');
            } elseif (!Schema::hasColumn('users', 'primary_name')) {
                $table->string('primary_name')->nullable();
            }

            if (Schema::hasColumn('users', 'primary_contact_name')) {
                $table->dropColumn('primary_contact_name');
            }
            
            if (Schema::hasColumn('users', 'secondary_contact_name')) {
                $table->renameColumn('secondary_contact_name', 'secondary_name');
            } else if (!Schema::hasColumn('users', 'secondary_name')) {
                $table->string('secondary_name')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('full_name')->nullable();
            $table->string('primary_contact_name')->nullable();
            
            $table->renameColumn('primary_name', 'name');
            
            if (Schema::hasColumn('users', 'secondary_name')) {
                $table->renameColumn('secondary_name', 'secondary_contact_name');
            }
        });
    }
};
