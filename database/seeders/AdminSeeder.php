<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Seed the default JeevaLink Technical Admin.
     */
    public function run(): void
    {
        $adminEmail = env('ADMIN_EMAIL');
        $adminPassword = env('ADMIN_PASSWORD');
        $adminMobile = env('ADMIN_MOBILE', '9000000001');

        if (empty($adminEmail) || empty($adminPassword)) {
            $this->command->warn('ADMIN_EMAIL or ADMIN_PASSWORD is not set in .env. Skipping admin creation.');
            return;
        }

        // Check if admin already exists
        if (DB::table('users')->where('email', $adminEmail)->exists()) {
            $this->command->info('Admin already exists. Skipping.');
            return;
        }

        DB::table('users')->insert([
            'full_name'              => env('ADMIN_NAME', 'JeevaLink Admin'),
            'email'                  => $adminEmail,
            'mobile'                 => $adminMobile,
            'password_hash'          => Hash::make($adminPassword),

            'role'                   => 'admin',
            'blood_group'            => 'N/A',
            'city'                   => 'Kochi',
            'district'               => 'Ernakulam',

            'full_address'           => null,
            'weight'                 => null,
            'date_of_birth'          => null,
            'dob'                    => null,
            'last_donated_date'      => null,
            'profile_picture'        => null,
            'id_proof_front'         => null,
            'id_proof_back'          => null,
            'sex'                    => null,

            'available_for_donation' => false,
            'reward_points'          => 0,
            'lives_saved'            => 0,
            'total_donations'        => 0,

            'status'                 => 'Active',
            'eligibility_status'     => 'Eligible',
            'eligibility_checked_at' => now(),
            'is_verified'            => true,

            'expo_push_token'        => null,
            'fcm_token'              => null,

            'latitude'               => null,
            'longitude'              => null,

            'notification_enabled'   => true,

            'pincode'                => null,
            'organization_name'      => 'JeevaLink',
            'volunteer_type'         => null,
            'secondary_phone'        => null,
            'pin_code'               => null,
            'address'                => null,
            'remarks'                => 'System Generated Technical Admin',

            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $this->command->info('');
        $this->command->info('=====================================');
        $this->command->info('✅ Technical Admin Created Successfully');
        $this->command->info('=====================================');
        $this->command->info('Name     : ' . env('ADMIN_NAME', 'JeevaLink Admin'));
        $this->command->info('Email    : ' . $adminEmail);
        $this->command->info('Password : (hidden)');
        $this->command->info('=====================================');
    }
}