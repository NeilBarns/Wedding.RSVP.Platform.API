<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGuestApiTest extends TestCase
{
    use RefreshDatabase;

    private Invitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();
        $wedding = Wedding::factory()->create();
        $this->invitation = Invitation::factory()->for($wedding)->create();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMINISTRATOR]));
    }

    public function test_guest_can_be_created_for_every_type_with_pending_status(): void
    {
        foreach (Guest::TYPES as $index => $type) {
            $this->postJson("/api/admin/invitations/{$this->invitation->id}/guests", [
                'fullName' => " Guest {$index} ",
                'guestType' => $type,
                'sortOrder' => $index,
                'dietaryRequirements' => ' ',
                'accessibilityRequirements' => null,
                'attendanceStatus' => Guest::ATTENDANCE_ATTENDING,
            ])->assertCreated()
                ->assertJsonPath('data.guestType', $type)
                ->assertJsonPath('data.attendanceStatus', Guest::ATTENDANCE_PENDING)
                ->assertJsonPath('data.dietaryRequirements', null);
        }

        $this->postJson("/api/admin/invitations/{$this->invitation->id}/guests", [
            'fullName' => 'Invalid',
            'guestType' => 'plus_one',
            'sortOrder' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('guestType');
    }

    public function test_guest_update_is_nested_normalized_and_cannot_change_attendance(): void
    {
        $guest = Guest::factory()->for($this->invitation)->attending()->create();
        $otherInvitation = Invitation::factory()->create();
        $outsideGuest = Guest::factory()->for($otherInvitation)->create();

        $this->putJson("/api/admin/invitations/{$this->invitation->id}/guests/{$outsideGuest->id}", $this->guestPayload())
            ->assertNotFound();

        $this->putJson("/api/admin/invitations/{$this->invitation->id}/guests/{$guest->id}", $this->guestPayload([
            'attendanceStatus' => Guest::ATTENDANCE_DECLINED,
        ]))->assertOk()
            ->assertJsonPath('data.fullName', 'Sofia Marie Dela Cruz')
            ->assertJsonPath('data.dietaryRequirements', null)
            ->assertJsonPath('data.attendanceStatus', Guest::ATTENDANCE_ATTENDING);
    }

    public function test_archived_invitation_blocks_all_guest_mutations(): void
    {
        $this->invitation->update(['status' => Invitation::STATUS_ARCHIVED]);
        $guest = Guest::factory()->for($this->invitation)->create();

        $this->postJson("/api/admin/invitations/{$this->invitation->id}/guests", $this->guestPayload())
            ->assertConflict();
        $this->putJson("/api/admin/invitations/{$this->invitation->id}/guests/{$guest->id}", $this->guestPayload())
            ->assertConflict();
        $this->deleteJson("/api/admin/invitations/{$this->invitation->id}/guests/{$guest->id}")
            ->assertConflict();
    }

    public function test_guest_delete_enforces_pending_and_last_guest_rules(): void
    {
        $last = Guest::factory()->for($this->invitation)->create();
        $this->deleteJson("/api/admin/invitations/{$this->invitation->id}/guests/{$last->id}")
            ->assertConflict();

        $pending = Guest::factory()->for($this->invitation)->create();
        $this->deleteJson("/api/admin/invitations/{$this->invitation->id}/guests/{$pending->id}")
            ->assertNoContent();

        foreach ([Guest::ATTENDANCE_ATTENDING, Guest::ATTENDANCE_DECLINED] as $status) {
            $guest = Guest::factory()->for($this->invitation)->create(['attendance_status' => $status]);
            $this->deleteJson("/api/admin/invitations/{$this->invitation->id}/guests/{$guest->id}")
                ->assertConflict();
        }
    }

    public function test_invitation_and_guest_wedding_scope_is_enforced(): void
    {
        $outsideWedding = Wedding::factory()->create();
        $outsideInvitation = Invitation::factory()->for($outsideWedding)->create();
        $outsideGuest = Guest::factory()->for($outsideInvitation)->create();

        $this->postJson("/api/admin/invitations/{$outsideInvitation->id}/guests", $this->guestPayload())
            ->assertNotFound();
        $this->putJson("/api/admin/invitations/{$outsideInvitation->id}/guests/{$outsideGuest->id}", $this->guestPayload())
            ->assertNotFound();
    }

    private function guestPayload(array $overrides = []): array
    {
        return array_replace([
            'fullName' => ' Sofia Marie Dela Cruz ',
            'guestType' => Guest::TYPE_CHILD,
            'sortOrder' => 2,
            'dietaryRequirements' => ' ',
            'accessibilityRequirements' => null,
        ], $overrides);
    }
}
