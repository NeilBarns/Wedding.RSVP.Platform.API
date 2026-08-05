<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_roles_password_hashing_and_serialization(): void
    {
        $administrator = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.test',
            'password' => 'test-password',
        ])->refresh();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->assertSame(User::ROLE_ADMINISTRATOR, $administrator->role);
        $this->assertTrue($administrator->isAdministrator());
        $this->assertFalse($administrator->isOwner());
        $this->assertTrue($owner->isOwner());
        $this->assertFalse($owner->isAdministrator());
        $this->assertTrue(Hash::check('test-password', $administrator->password));
        $this->assertArrayNotHasKey('password', $administrator->toArray());
        $this->assertArrayNotHasKey('remember_token', $administrator->toArray());
    }
}
