<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_administrator_can_access_admin_ping(): void
    {
        foreach ([User::ROLE_OWNER, User::ROLE_ADMINISTRATOR] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/api/admin/ping')
                ->assertOk()
                ->assertExactJson(['status' => 'ok']);
        }
    }

    public function test_admin_ping_requires_authentication(): void
    {
        $this->getJson('/api/admin/ping')->assertUnauthorized();
    }

    public function test_existing_public_routes_remain_public(): void
    {
        $this->getJson('/api/health')->assertOk();
        $this->getJson('/api/wedding')->assertNotFound();
    }
}
