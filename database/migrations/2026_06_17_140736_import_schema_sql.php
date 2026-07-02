<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip raw PostgreSQL schema on SQLite – Laravel migrations handle all tables
        if (config('database.default') === 'sqlite') {
            return;
        }

        $schemaPath = base_path('schema.sql');
        if (File::exists($schemaPath)) {
            DB::unprepared(File::get($schemaPath));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('
            DROP TABLE IF EXISTS complaints CASCADE;
            DROP TABLE IF EXISTS notifications CASCADE;
            DROP TABLE IF EXISTS blood_requests CASCADE;
            DROP TABLE IF EXISTS users CASCADE;
            DROP TYPE IF EXISTS user_role;
            DROP TYPE IF EXISTS user_status;
            DROP TYPE IF EXISTS urgency_level;
            DROP TYPE IF EXISTS request_status;
            DROP TYPE IF EXISTS notification_type;
            DROP TYPE IF EXISTS complaint_status;
        ');
    }
};
