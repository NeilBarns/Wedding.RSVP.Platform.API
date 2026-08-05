<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInvitationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_routes_require_authentication(): void
    {
        foreach ([
            ['getJson', '/api/admin/invitations'],
            ['postJson', '/api/admin/invitations'],
            ['getJson', '/api/admin/invitations/1'],
            ['putJson', '/api/admin/invitations/1'],
            ['deleteJson', '/api/admin/invitations/1'],
            ['postJson', '/api/admin/invitations/1/mark-ready'],
            ['postJson', '/api/admin/invitations/1/lock'],
            ['postJson', '/api/admin/invitations/1/reopen'],
            ['postJson', '/api/admin/invitations/1/archive'],
            ['postJson', '/api/admin/invitations/1/regenerate-token'],
            ['postJson', '/api/admin/invitations/1/guests'],
            ['putJson', '/api/admin/invitations/1/guests/1'],
            ['deleteJson', '/api/admin/invitations/1/guests/1'],
        ] as [$method, $uri]) {
            $this->{$method}($uri)->assertUnauthorized();
        }
    }

    public function test_list_is_scoped_paginated_searchable_filterable_and_counted(): void
    {
        $current = Wedding::factory()->create();
        $otherWedding = Wedding::factory()->create();
        $matching = Invitation::factory()->for($current)->ready()->create([
            'display_name' => 'Dela Cruz Family',
            'internal_notes' => 'Not exposed',
        ]);
        Guest::factory()->for($matching)->attending()->create(['full_name' => 'Juan Dela Cruz']);
        Guest::factory()->for($matching)->declined()->create();
        Guest::factory()->for($matching)->create();
        Invitation::factory()->for($current)->create(['display_name' => 'Other Family']);
        Invitation::factory()->for($otherWedding)->create(['display_name' => 'Outside Family']);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))
            ->getJson('/api/admin/invitations?search=juan&status=ready&perPage=1&sortBy=displayName&sortDirection=asc')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('data.0.guestCount', 3)
            ->assertJsonPath('data.0.attendingCount', 1)
            ->assertJsonPath('data.0.declinedCount', 1)
            ->assertJsonPath('data.0.pendingCount', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonMissing(['token_hash', 'tokenHash', 'internalNotes']);

        $this->getJson('/api/admin/invitations?search=Dela%20Cruz')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/admin/invitations?status=invalid')->assertUnprocessable();
        $this->getJson('/api/admin/invitations?sortBy=tokenHash')->assertUnprocessable();
        $this->getJson('/api/admin/invitations?sortDirection=sideways')->assertUnprocessable();
    }

    public function test_owner_and_administrator_can_list_and_missing_wedding_returns_404(): void
    {
        Wedding::factory()->create();

        foreach ([User::ROLE_OWNER, User::ROLE_ADMINISTRATOR] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/api/admin/invitations')
                ->assertOk();
        }

        Wedding::query()->delete();
        $this->getJson('/api/admin/invitations')
            ->assertNotFound();
    }

    public function test_create_persists_only_hash_and_returns_one_time_url_and_token(): void
    {
        $wedding = Wedding::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/admin/invitations', $this->createPayload())
            ->assertCreated()
            ->assertJsonPath('data.invitation.status', Invitation::STATUS_DRAFT)
            ->assertJsonPath('data.invitation.guestCount', 2)
            ->assertJsonMissing(['token_hash', 'tokenHash']);

        $rawToken = $response->json('data.access.token');
        $invitation = Invitation::query()->sole();

        $this->assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{43}\z/', $rawToken);
        $this->assertSame(hash('sha256', $rawToken), $invitation->token_hash);
        $this->assertNotSame($rawToken, $invitation->token_hash);
        $this->assertSame($wedding->id, $invitation->wedding_id);
        $this->assertSame('http://localhost:5173/invite/'.$rawToken, $response->json('data.access.invitationUrl'));
        $this->assertDatabaseCount('guests', 2);
        $this->assertDatabaseHas('guests', [
            'full_name' => 'Juan Dela Cruz',
            'attendance_status' => Guest::ATTENDANCE_PENDING,
        ]);

        $second = $this->postJson('/api/admin/invitations', $this->createPayload([
            'displayName' => 'Second Family',
        ]))->assertCreated();
        $this->assertNotSame($rawToken, $second->json('data.access.token'));
    }

    public function test_create_requires_a_guest_and_current_wedding(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/admin/invitations', $this->createPayload())->assertNotFound();

        Wedding::factory()->create();
        $payload = $this->createPayload();
        $payload['guests'] = [];
        $this->postJson('/api/admin/invitations', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guests');
    }

    public function test_detail_orders_guests_returns_notes_and_never_reconstructs_access(): void
    {
        $wedding = Wedding::factory()->create();
        $invitation = Invitation::factory()->for($wedding)->create(['internal_notes' => 'Private note']);
        $later = Guest::factory()->for($invitation)->create(['sort_order' => 2]);
        $first = Guest::factory()->for($invitation)->create(['sort_order' => 1]);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/admin/invitations/{$invitation->id}")
            ->assertOk()
            ->assertJsonPath('data.internalNotes', 'Private note')
            ->assertJsonPath('data.invitationUrl', null)
            ->assertJsonPath('data.guests.0.id', $first->id)
            ->assertJsonPath('data.guests.1.id', $later->id)
            ->assertJsonMissing(['token_hash', 'tokenHash']);
    }

    public function test_invitation_scope_and_profile_update_are_enforced(): void
    {
        $current = Wedding::factory()->create();
        $other = Wedding::factory()->create();
        $invitation = Invitation::factory()->for($current)->ready()->create();
        $outside = Invitation::factory()->for($other)->create();
        $oldHash = $invitation->token_hash;

        $this->actingAs(User::factory()->create())
            ->getJson("/api/admin/invitations/{$outside->id}")
            ->assertNotFound();

        $this->putJson("/api/admin/invitations/{$invitation->id}", [
            'displayName' => ' Updated Household ',
            'contactPersonName' => ' ',
            'contactNumber' => '',
            'email' => ' PERSON@EXAMPLE.COM ',
            'internalNotes' => "\t",
            'status' => Invitation::STATUS_ARCHIVED,
            'weddingId' => $other->id,
            'tokenHash' => str_repeat('a', 64),
        ])->assertOk()
            ->assertJsonPath('data.displayName', 'Updated Household')
            ->assertJsonPath('data.email', 'person@example.com')
            ->assertJsonPath('data.contactPersonName', null);

        $invitation->refresh();
        $this->assertSame(Invitation::STATUS_READY, $invitation->status);
        $this->assertSame($current->id, $invitation->wedding_id);
        $this->assertSame($oldHash, $invitation->token_hash);
    }

    private function createPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'displayName' => ' Dela Cruz Family ',
            'contactPersonName' => ' Juan Dela Cruz ',
            'contactNumber' => ' 09171234567 ',
            'email' => ' JUAN@EXAMPLE.COM ',
            'internalNotes' => " Bride's relatives ",
            'guests' => [
                [
                    'fullName' => ' Juan Dela Cruz ',
                    'guestType' => Guest::TYPE_ADULT,
                    'sortOrder' => 0,
                ],
                [
                    'fullName' => 'Maria Dela Cruz',
                    'guestType' => Guest::TYPE_ADULT,
                    'sortOrder' => 1,
                ],
            ],
        ], $overrides);
    }
}
