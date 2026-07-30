<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Helpers\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SuperAdminCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_technical_admin_can_create_super_admin_without_500_error(): void
    {
        $techAdmin = User::create([
            'name' => 'Tech Admin',
            'full_name' => 'Tech Admin',
            'email' => 'techadmin@example.com',
            'mobile' => '9998887770',
            'password_hash' => bcrypt('password'),
            'role' => 'technical_admin',
            'city' => 'Ernakulam',
            'district' => 'Ernakulam',
            'status' => 'Active',
            'is_verified' => true,
        ]);

        $token = JWT::generateToken($techAdmin->id, $techAdmin->role);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
                         ->postJson('/api/v1/technical-admin/super-admins', [
                             'full_name' => 'District Super Admin',
                             'email' => 'superadmin.dist@example.com',
                             'mobile' => '9876543210',
                             'district' => 'Ernakulam',
                         ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Super Admin created successfully!',
                 ]);

        $this->assertDatabaseHas('users', [
            'email' => 'superadmin.dist@example.com',
            'role' => 'super_admin',
            'district' => 'Ernakulam',
        ]);
    }
}
