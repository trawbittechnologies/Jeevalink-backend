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
                $table->dropColumn('full_name');
            }
            if (Schema::hasColumn('users', 'primary_contact_name')) {
                $table->dropColumn('primary_contact_name');
            }
            
            $table->renameColumn('name', 'primary_name');
            
            if (Schema::hasColumn('users', 'secondary_contact_name')) {
                $table->renameColumn('secondary_contact_name', 'secondary_name');
            } else {
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
