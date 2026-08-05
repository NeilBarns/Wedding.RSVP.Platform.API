<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInvitationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Wedding $wedding;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wedding = Wedding::factory()->create();
        $this->actingAs(User::factory()->create());
    }

    public function test_mark_ready_requires_draft_with_guest_and_is_idempotent(): void
    {
        $invitation = Invitation::factory()->for($this->wedding)->create();
        $this->postJson("/api/admin/invitations/{$invitation->id}/mark-ready")->assertConflict();

        Guest::factory()->for($invitation)->create();
        $this->postJson("/api/admin/invitations/{$invitation->id}/mark-ready")
            ->assertOk()
            ->assertJsonPath('data.status', Invitation::STATUS_READY);
        $this->postJson("/api/admin/invitations/{$invitation->id}/mark-ready")->assertOk();

        $invitation->update(['status' => Invitation::STATUS_SUBMITTED]);
        $this->postJson("/api/admin/invitations/{$invitation->id}/mark-ready")->assertConflict();
    }

    public function test_lock_and_reopen_follow_submission_history(): void
    {
        $ready = Invitation::factory()->for($this->wedding)->ready()->create();
        Guest::factory()->for($ready)->create();

        $this->postJson("/api/admin/invitations/{$ready->id}/lock")
            ->assertOk()
            ->assertJsonPath('data.status', Invitation::STATUS_LOCKED)
            ->assertJsonPath('data.lockedAt', fn ($value) => $value !== null);
        $this->postJson("/api/admin/invitations/{$ready->id}/lock")->assertOk();
        $this->postJson("/api/admin/invitations/{$ready->id}/reopen")
            ->assertOk()
            ->assertJsonPath('data.status', Invitation::STATUS_READY)
            ->assertJsonPath('data.lockedAt', null);

        $submitted = Invitation::factory()->for($this->wedding)->submitted()->create();
        Guest::factory()->for($submitted)->create();
        $this->postJson("/api/admin/invitations/{$submitted->id}/lock")->assertOk();
        $this->postJson("/api/admin/invitations/{$submitted->id}/reopen")
            ->assertOk()
            ->assertJsonPath('data.status', Invitation::STATUS_SUBMITTED);

        $this->postJson("/api/admin/invitations/{$submitted->id}/reopen")->assertConflict();
    }

    public function test_archive_is_supported_and_idempotent(): void
    {
        foreach ([
            Invitation::STATUS_DRAFT,
            Invitation::STATUS_READY,
            Invitation::STATUS_SUBMITTED,
            Invitation::STATUS_LOCKED,
            Invitation::STATUS_ARCHIVED,
        ] as $status) {
            $invitation = Invitation::factory()->for($this->wedding)->create(['status' => $status]);
            Guest::factory()->for($invitation)->create();

            $this->postJson("/api/admin/invitations/{$invitation->id}/archive")
                ->assertOk()
                ->assertJsonPath('data.status', Invitation::STATUS_ARCHIVED);
        }
    }

    public function test_delete_rules_and_guest_cascade(): void
    {
        foreach ([Invitation::STATUS_DRAFT, Invitation::STATUS_READY] as $status) {
            $invitation = Invitation::factory()->for($this->wedding)->create(['status' => $status]);
            $guest = Guest::factory()->for($invitation)->create();
            $this->deleteJson("/api/admin/invitations/{$invitation->id}")->assertNoContent();
            $this->assertDatabaseMissing('guests', ['id' => $guest->id]);
        }

        foreach ([Invitation::STATUS_SUBMITTED, Invitation::STATUS_LOCKED, Invitation::STATUS_ARCHIVED] as $status) {
            $invitation = Invitation::factory()->for($this->wedding)->create(['status' => $status]);
            $this->deleteJson("/api/admin/invitations/{$invitation->id}")->assertConflict();
        }
    }

    public function test_token_regeneration_replaces_hash_without_changing_household(): void
    {
        $invitation = Invitation::factory()->for($this->wedding)->ready()->create();
        Guest::factory()->for($invitation)->count(2)->create();
        $oldHash = $invitation->token_hash;

        $response = $this->postJson("/api/admin/invitations/{$invitation->id}/regenerate-token")
            ->assertOk()
            ->assertJsonMissing(['token_hash', 'tokenHash']);
        $rawToken = $response->json('data.access.token');

        $this->assertNotSame($oldHash, $invitation->refresh()->token_hash);
        $this->assertSame(hash('sha256', $rawToken), $invitation->token_hash);
        $this->assertSame(Invitation::STATUS_READY, $invitation->status);
        $this->assertCount(2, $invitation->guests);
        $this->assertStringEndsWith('/invite/'.$rawToken, $response->json('data.access.invitationUrl'));

        $invitation->update(['status' => Invitation::STATUS_ARCHIVED]);
        $this->postJson("/api/admin/invitations/{$invitation->id}/regenerate-token")->assertConflict();
    }
}
