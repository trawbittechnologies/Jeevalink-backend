<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Create the default JeevaLink admin account.
     *
     * Credentials:
     *   Email    : admin@jeevalink.org
     *   Password : Admin@2026
     */
    public function run(): void
    {
        $adminEmail = env('ADMIN_EMAIL');
        $adminPassword = env('ADMIN_PASSWORD');
        $adminMobile = env('ADMIN_MOBILE', '9000000001');

        if (!$adminEmail || !$adminPassword) {
            $this->command->warn('ADMIN_EMAIL or ADMIN_PASSWORD is not set in .env. Skipping admin user creation.');
            return;
        }

        // Avoid duplicate seeding
        if (DB::table('users')->where('email', $adminEmail)->exists()) {
            $this->command->info('Admin user already exists. Skipping.');
            return;
        }

        DB::table('users')->insert([
            'name'                   => 'JeevaLink Admin',
            'full_name'              => 'JeevaLink Admin',
            'email'                  => $adminEmail,
            'mobile'                 => $adminMobile,
            'password'               => Hash::make($adminPassword),
            'password_hash'          => Hash::make($adminPassword),
            'role'                   => 'admin',
            'blood_group'            => 'N/A',
            'city'                   => 'Kochi',
            'district'               => 'Ernakulam',
            'status'                 => 'Active',
            'is_verified'            => true,
            'available_for_donation' => false,
            'reward_points'          => 0,
            'lives_saved'            => 0,
            'total_donations'        => 0,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $this->command->info('✅ Admin user created successfully!');
        $this->command->info('   Email    : ' . $adminEmail);
        $this->command->info('   Password : (hidden)');
    }
}
