<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\BloodRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BlockAdminMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_block_admin_metrics_endpoint_returns_200_without_sql_errors(): void
    {
        $blockAdmin = User::create([
            'name' => 'Test Block Admin',
            'full_name' => 'Test Block Admin',
            'email' => 'blockadmin@example.com',
            'mobile' => '9876543210',
            'password_hash' => bcrypt('password'),
            'role' => 'block_admin',
            'city' => 'Ernakulam',
            'district' => 'Ernakulam',
            'status' => 'Active',
            'is_verified' => true,
        ]);

        BloodRequest::create([
            'requested_by' => $blockAdmin->id,
            'patient_name' => 'John Doe',
            'blood_group' => 'A+',
            'units_required' => 2,
            'hospital_name' => 'City Hospital',
            'hospital_address' => 'Main Road',
            'city' => 'Ernakulam',
            'district' => 'Ernakulam',
            'contact_number' => '9876543210',
            'required_by_date' => now()->addDays(2),
            'urgency_level' => 'Normal',
            'status' => 'Pending',
        ]);

        $token = \App\Helpers\JWT::generateToken($blockAdmin->id, $blockAdmin->role);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
                         ->getJson('/api/v1/block-admin/metrics');

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'city' => 'Ernakulam',
                         'district' => 'Ernakulam',
                         'total_requests' => 1,
                         'pending_requests' => 1,
                     ]
                 ]);
    }
}
