<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Helpers\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecondaryContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_secondary_contact_name_and_number_are_saved_and_returned(): void
    {
        $techAdmin = User::create([
            'name' => 'Tech Admin',
            'full_name' => 'Tech Admin',
            'email' => 'techadmin2@example.com',
            'mobile' => '9998887771',
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
                             'full_name' => 'Admin 1 & Admin 2',
                             'email' => 'superadmin.sec@example.com',
                             'mobile' => '9876543211',
                             'district' => 'Ernakulam',
                             'secondaryContactName' => 'Admin 2 Name',
                             'secondaryContactNumber' => '9876543222',
                             'whatsapp_number' => '9876543211',
                         ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'superadmin.sec@example.com',
            'secondary_contact_name' => 'Admin 2 Name',
            'secondary_phone' => '9876543222',
            'whatsapp_number' => '9876543211',
        ]);

        $listResponse = $this->withHeader('Authorization', "Bearer {$token}")
                             ->getJson('/api/v1/technical-admin/super-admins');

        $listResponse->assertStatus(200)
                     ->assertJsonFragment([
                         'secondaryContactName' => 'Admin 2 Name',
                         'secondaryContactNumber' => '9876543222',
                     ]);
    }
}
