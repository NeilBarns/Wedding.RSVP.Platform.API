<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\RsvpRevision;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PublicRsvpApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ready_household_can_submit_a_complete_public_rsvp(): void
    {
        $this->travelTo('2026-08-06 05:00:00');
        [$invitation, $token, $first, $second] = $this->household();
        $invitation->update([
            'first_opened_at' => '2026-08-01 01:00:00',
            'last_opened_at' => '2026-08-02 02:00:00',
        ]);
        $adminContact = [
            $invitation->contact_number,
            $invitation->email,
            $invitation->contact_person_name,
        ];

        $this->putJson("/api/invitations/{$token}/rsvp", [
            'guests' => [
                [
                    'id' => $second->id,
                    'attendanceStatus' => Guest::ATTENDANCE_DECLINED,
                    'dietaryRequirements' => 'ignored',
                    'accessibilityRequirements' => 'ignored',
                ],
                [
                    'id' => $first->id,
                    'attendanceStatus' => Guest::ATTENDANCE_ATTENDING,
                    'dietaryRequirements' => ' No shellfish ',
                    'accessibilityRequirements' => ' Wheelchair access ',
                ],
            ],
            'contactNumber' => ' 09171234567 ',
            'email' => ' JUAN@EXAMPLE.COM ',
            'message' => ' We are excited! ',
        ])->assertOk()
            ->assertJsonPath('data.invitation.status', Invitation::STATUS_SUBMITTED)
            ->assertJsonPath('data.invitation.canRespond', true)
            ->assertJsonPath('data.invitation.hasSubmitted', true)
            ->assertJsonPath('data.invitation.submittedAt', '2026-08-06T05:00:00+00:00')
            ->assertJsonPath('data.invitation.responseContactNumber', '09171234567')
            ->assertJsonPath('data.invitation.responseEmail', 'juan@example.com')
            ->assertJsonPath('data.invitation.messageToCouple', 'We are excited!')
            ->assertJsonPath('data.invitation.guests.0.id', $first->id)
            ->assertJsonPath('data.invitation.guests.0.dietaryRequirements', 'No shellfish')
            ->assertJsonPath('data.invitation.guests.1.id', $second->id)
            ->assertJsonPath('data.invitation.guests.1.dietaryRequirements', null)
            ->assertJsonPath('data.revisionNumber', 1)
            ->assertJsonMissing([
                'token_hash',
                'tokenHash',
                $token,
                'contactPersonName',
                'internalNotes',
                'response_snapshot',
            ]);

        $invitation->refresh();
        $this->assertSame(Invitation::STATUS_SUBMITTED, $invitation->status);
        $this->assertSame($adminContact, [
            $invitation->contact_number,
            $invitation->email,
            $invitation->contact_person_name,
        ]);
        $this->assertTrue($invitation->first_opened_at->equalTo('2026-08-01 01:00:00'));
        $this->assertTrue($invitation->last_opened_at->equalTo('2026-08-02 02:00:00'));
        $this->assertSame('attending', $first->fresh()->attendance_status);
        $this->assertSame('declined', $second->fresh()->attendance_status);
        $this->assertDatabaseHas('rsvp_revisions', [
            'invitation_id' => $invitation->id,
            'revision_number' => 1,
        ]);
    }

    public function test_submitted_household_can_edit_and_previous_revision_stays_immutable(): void
    {
        [$invitation, $token, $first, $second] = $this->household();

        $this->travelTo('2026-08-06 05:00:00');
        $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($first, $second))
            ->assertOk();
        $firstSnapshot = RsvpRevision::query()->sole()->response_snapshot;

        $this->travelTo('2026-08-07 06:30:00');
        $this->putJson("/api/invitations/{$token}/rsvp", [
            'guests' => [
                ['id' => $first->id, 'attendanceStatus' => Guest::ATTENDANCE_DECLINED],
                [
                    'id' => $second->id,
                    'attendanceStatus' => Guest::ATTENDANCE_ATTENDING,
                    'dietaryRequirements' => 'Vegetarian',
                ],
            ],
            'contactNumber' => '',
            'email' => ' NEW@EXAMPLE.COM ',
            'message' => ' ',
        ])->assertOk()
            ->assertJsonPath('data.revisionNumber', 2)
            ->assertJsonPath('data.invitation.responseContactNumber', null)
            ->assertJsonPath('data.invitation.responseEmail', 'new@example.com')
            ->assertJsonPath('data.invitation.messageToCouple', null)
            ->assertJsonPath('data.invitation.guests.0.attendanceStatus', Guest::ATTENDANCE_DECLINED)
            ->assertJsonPath('data.invitation.guests.0.dietaryRequirements', null)
            ->assertJsonPath('data.invitation.guests.1.attendanceStatus', Guest::ATTENDANCE_ATTENDING);

        $this->assertCount(2, $invitation->rsvpRevisions()->get());
        $this->assertSame($firstSnapshot, $invitation->rsvpRevisions()->where('revision_number', 1)->sole()->response_snapshot);
        $this->assertTrue($invitation->fresh()->submitted_at->equalTo('2026-08-07 06:30:00'));
    }

    public function test_revision_snapshot_is_complete_ordered_safe_and_historical(): void
    {
        [$invitation, $token, $first, $second] = $this->household();

        $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($first, $second))
            ->assertOk();

        $revision = RsvpRevision::query()->sole();
        $snapshot = $revision->response_snapshot;
        $this->assertSame($invitation->id, $snapshot['invitation']['id']);
        $this->assertSame([$first->id, $second->id], array_column($snapshot['guests'], 'id'));
        $this->assertSame($first->full_name, $snapshot['guests'][0]['fullName']);
        $this->assertArrayNotHasKey('tokenHash', $snapshot['invitation']);
        $this->assertArrayNotHasKey('internalNotes', $snapshot['invitation']);
        $this->assertArrayNotHasKey('contactNumber', $snapshot['invitation']);

        $originalName = $first->full_name;
        $first->update(['full_name' => 'Changed Later']);
        $this->assertSame($originalName, $revision->fresh()->response_snapshot['guests'][0]['fullName']);
        $this->assertTrue($invitation->fresh()->submitted_at->equalTo($revision->submitted_at));
    }

    public function test_revision_failure_rolls_back_the_entire_household_update(): void
    {
        [$invitation, $token, $first, $second] = $this->household();
        RsvpRevision::creating(fn () => throw new RuntimeException('Forced revision failure.'));

        try {
            $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($first, $second));
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced revision failure.', $exception->getMessage());
        }

        $this->assertSame(Invitation::STATUS_READY, $invitation->fresh()->status);
        $this->assertSame(Guest::ATTENDANCE_PENDING, $first->fresh()->attendance_status);
        $this->assertSame(Guest::ATTENDANCE_PENDING, $second->fresh()->attendance_status);
        $this->assertDatabaseCount('rsvp_revisions', 0);
    }

    public function test_guest_update_failure_rolls_back_the_entire_household_update(): void
    {
        [$invitation, $token, $first, $second] = $this->household();
        Guest::updating(function (Guest $guest) use ($second) {
            if ($guest->id === $second->id) {
                throw new RuntimeException('Forced guest failure.');
            }
        });

        try {
            $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($first, $second));
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced guest failure.', $exception->getMessage());
        }

        $this->assertSame(Invitation::STATUS_READY, $invitation->fresh()->status);
        $this->assertSame(Guest::ATTENDANCE_PENDING, $first->fresh()->attendance_status);
        $this->assertSame(Guest::ATTENDANCE_PENDING, $second->fresh()->attendance_status);
        $this->assertDatabaseCount('rsvp_revisions', 0);
    }

    public function test_deleting_invitation_cascades_to_revisions(): void
    {
        [$invitation, $token, $first, $second] = $this->household();
        $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($first, $second))->assertOk();

        $invitation->delete();

        $this->assertDatabaseCount('rsvp_revisions', 0);
    }

    public function test_public_get_reloads_response_fields_without_mutating_rsvp_state(): void
    {
        [$invitation, $token, $first, $second] = $this->household();
        $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($first, $second))->assertOk();
        $revisionCount = $invitation->rsvpRevisions()->count();
        $submittedAt = $invitation->fresh()->submitted_at;

        $this->getJson("/api/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.invitation.responseContactNumber', '09171234567')
            ->assertJsonPath('data.invitation.responseEmail', 'juan@example.com')
            ->assertJsonPath('data.invitation.messageToCouple', 'Excited!');

        $this->assertSame($revisionCount, $invitation->rsvpRevisions()->count());
        $this->assertTrue($submittedAt->equalTo($invitation->fresh()->submitted_at));
    }

    private function household(): array
    {
        $wedding = Wedding::factory()->create([
            'status' => Wedding::STATUS_PUBLISHED,
            'rsvp_deadline' => '2026-12-01',
        ]);
        $token = str_repeat('R', 43);
        $invitation = Invitation::factory()->for($wedding)->ready()->create([
            'token_hash' => hash('sha256', $token),
            'contact_person_name' => 'Admin Contact',
            'contact_number' => 'admin-number',
            'email' => 'admin@example.com',
            'internal_notes' => 'Private',
        ]);
        $second = Guest::factory()->for($invitation)->create(['sort_order' => 2]);
        $first = Guest::factory()->for($invitation)->create(['sort_order' => 1]);

        return [$invitation, $token, $first, $second];
    }

    private function payload(Guest $first, Guest $second): array
    {
        return [
            'guests' => [
                [
                    'id' => $first->id,
                    'attendanceStatus' => Guest::ATTENDANCE_ATTENDING,
                    'dietaryRequirements' => 'No shellfish',
                ],
                [
                    'id' => $second->id,
                    'attendanceStatus' => Guest::ATTENDANCE_DECLINED,
                ],
            ],
            'contactNumber' => '09171234567',
            'email' => 'juan@example.com',
            'message' => 'Excited!',
        ];
    }
}
