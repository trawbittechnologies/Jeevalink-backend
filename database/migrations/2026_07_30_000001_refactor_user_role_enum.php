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

        if ($driver === 'pgsql') {
            DB::statement("
                DO $$
                BEGIN
                    IF EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_role') THEN
                        ALTER TYPE user_role RENAME TO user_role_old;
                        CREATE TYPE user_role AS ENUM ('technical_admin', 'super_admin', 'block_admin', 'volunteer', 'unit_squad', 'user');
                        ALTER TABLE users ALTER COLUMN role DROP DEFAULT;
                        ALTER TABLE users ALTER COLUMN role TYPE user_role USING (
                            CASE role::text
                                WHEN 'admin' THEN 'technical_admin'::user_role
                                WHEN 'donor' THEN 'user'::user_role
                                WHEN 'hospital' THEN 'user'::user_role
                                ELSE role::text::user_role
                            END
                        );
                        ALTER TABLE users ALTER COLUMN role SET DEFAULT 'user'::user_role;
                        DROP TYPE user_role_old;
                    END IF;
                END $$;
            ");
        } else {
            // SQLite or MySQL
            DB::table('users')->where('role', 'admin')->update(['role' => 'technical_admin']);
            DB::table('users')->where('role', 'donor')->update(['role' => 'user']);
            DB::table('users')->where('role', 'hospital')->update(['role' => 'user']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("
                DO $$
                BEGIN
                    IF EXISTS (SELECT 1 FROM pg_type WHERE typname = 'user_role') THEN
                        ALTER TYPE user_role RENAME TO user_role_old;
                        CREATE TYPE user_role AS ENUM ('donor', 'volunteer', 'hospital', 'admin');
                        ALTER TABLE users ALTER COLUMN role DROP DEFAULT;
                        ALTER TABLE users ALTER COLUMN role TYPE user_role USING (
                            CASE role::text
                                WHEN 'technical_admin' THEN 'admin'::user_role
                                WHEN 'super_admin' THEN 'admin'::user_role
                                WHEN 'block_admin' THEN 'admin'::user_role
                                WHEN 'unit_squad' THEN 'volunteer'::user_role
                                WHEN 'user' THEN 'donor'::user_role
                                ELSE 'donor'::user_role
                            END
                        );
                        ALTER TABLE users ALTER COLUMN role SET DEFAULT 'donor'::user_role;
                        DROP TYPE user_role_old;
                    END IF;
                END $$;
            ");
        } else {
            DB::table('users')->where('role', 'technical_admin')->update(['role' => 'admin']);
            DB::table('users')->where('role', 'super_admin')->update(['role' => 'admin']);
            DB::table('users')->where('role', 'block_admin')->update(['role' => 'admin']);
            DB::table('users')->where('role', 'unit_squad')->update(['role' => 'volunteer']);
            DB::table('users')->where('role', 'user')->update(['role' => 'donor']);
        }
    }
};
