<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Services\JeevalinkIdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class JeevalinkIdServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Since we created the new migration, running RefreshDatabase will run it
    }

    public function test_it_generates_correct_format_for_super_admin()
    {
        $user = new User();
        $user->role = 'super_admin';
        $user->district = 'Kasaragod';
        
        $service = new JeevalinkIdService();
        $id = $service->generateId($user);

        // First generation should be 0010
        // Kasaragod gets a code generated (e.g. KSD)
        // Format: JL-SD-[CODE]-0010
        $this->assertMatchesRegularExpression('/^JL-SD-[A-Z]{3}-0010$/', $id);
    }

    public function test_it_generates_correct_format_for_block_admin()
    {
        $user = new User();
        $user->role = 'block_admin';
        $user->city = 'Cheruvathur';
        
        $service = new JeevalinkIdService();
        $id = $service->generateId($user);

        $this->assertMatchesRegularExpression('/^JL-BA-[A-Z]{3}-0010$/', $id);
    }

    public function test_it_generates_correct_format_for_volunteer()
    {
        $user = new User();
        $user->role = 'volunteer';
        $user->city = 'Cheemeni';
        
        $service = new JeevalinkIdService();
        $id = $service->generateId($user);

        $this->assertMatchesRegularExpression('/^JL-VO-[A-Z]{3}-0010$/', $id);
    }

    public function test_it_generates_correct_format_for_user()
    {
        $user = new User();
        $user->role = 'user';
        $user->pincode = '671314';
        
        $service = new JeevalinkIdService();
        $id = $service->generateId($user);

        $this->assertMatchesRegularExpression('/^JL-US-[A-Z]{3}-0010$/', $id);
    }

    public function test_it_increments_sequence_for_same_role_and_place()
    {
        $service = new JeevalinkIdService();
        
        $user1 = new User();
        $user1->role = 'super_admin';
        $user1->district = 'Kasaragod';
        
        $user2 = new User();
        $user2->role = 'super_admin';
        $user2->district = 'Kasaragod';
        
        $id1 = $service->generateId($user1);
        $id2 = $service->generateId($user2);

        $this->assertMatchesRegularExpression('/^JL-SD-[A-Z]{3}-0010$/', $id1);
        $this->assertMatchesRegularExpression('/^JL-SD-[A-Z]{3}-0011$/', $id2);
        
        // Ensure they share the same place code
        $code1 = explode('-', $id1)[2];
        $code2 = explode('-', $id2)[2];
        $this->assertEquals($code1, $code2);
    }

    public function test_it_uses_different_sequences_for_different_roles_same_place()
    {
        $service = new JeevalinkIdService();
        
        $user1 = new User();
        $user1->role = 'super_admin';
        $user1->district = 'Kasaragod';
        
        $user2 = new User();
        $user2->role = 'block_admin';
        $user2->city = 'Kasaragod';
        
        $id1 = $service->generateId($user1);
        $id2 = $service->generateId($user2);

        $this->assertMatchesRegularExpression('/^JL-SD-[A-Z]{3}-0010$/', $id1);
        $this->assertMatchesRegularExpression('/^JL-BA-[A-Z]{3}-0010$/', $id2);
    }
}
