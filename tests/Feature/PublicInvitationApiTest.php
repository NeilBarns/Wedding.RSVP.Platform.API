<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInvitationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ready_invitation_resolves_securely_with_ordered_public_safe_data(): void
    {
        [$wedding, $invitation, $token] = $this->accessibleInvitation();
        $second = Guest::factory()->for($invitation)->attending()->create([
            'full_name' => 'Maria Dela Cruz',
            'sort_order' => 2,
        ]);
        $first = Guest::factory()->for($invitation)->create([
            'full_name' => 'Juan Dela Cruz',
            'sort_order' => 1,
        ]);

        $this->getJson("/api/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.invitation.id', $invitation->id)
            ->assertJsonPath('data.invitation.displayName', $invitation->display_name)
            ->assertJsonPath('data.invitation.status', Invitation::STATUS_READY)
            ->assertJsonPath('data.invitation.canRespond', true)
            ->assertJsonPath('data.invitation.hasSubmitted', false)
            ->assertJsonPath('data.invitation.isLocked', false)
            ->assertJsonPath('data.invitation.guests.0.id', $first->id)
            ->assertJsonPath('data.invitation.guests.1.id', $second->id)
            ->assertJsonPath('data.invitation.guests.1.attendanceStatus', Guest::ATTENDANCE_ATTENDING)
            ->assertJsonPath('data.wedding.partnerOneName', $wedding->partner_one_name)
            ->assertJsonMissingPath('data.wedding.data')
            ->assertJsonMissing([
                'contactPersonName',
                'contactNumber',
                'email',
                'internalNotes',
                'token_hash',
                'tokenHash',
                'weddingId',
                'invitationId',
                'createdAt',
                'updatedAt',
                $token,
            ]);

        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
    }

    public function test_ready_submitted_and_locked_are_accessible_with_correct_derived_state(): void
    {
        $wedding = Wedding::factory()->create([
            'status' => Wedding::STATUS_PUBLISHED,
            'rsvp_deadline' => now()->addDays(5)->toDateString(),
        ]);

        $cases = [
            [Invitation::STATUS_READY, null, null, true, false, false],
            [Invitation::STATUS_SUBMITTED, now(), null, true, true, false],
            [Invitation::STATUS_LOCKED, now(), now(), false, true, true],
        ];

        foreach ($cases as $index => [$status, $submittedAt, $lockedAt, $canRespond, $hasSubmitted, $isLocked]) {
            $token = str_repeat(chr(65 + $index), 43);
            $invitation = Invitation::factory()->for($wedding)->create([
                'token_hash' => hash('sha256', $token),
                'status' => $status,
                'submitted_at' => $submittedAt,
                'locked_at' => $lockedAt,
            ]);
            Guest::factory()->for($invitation)->create();

            $this->getJson("/api/invitations/{$token}")
                ->assertOk()
                ->assertJsonPath('data.invitation.canRespond', $canRespond)
                ->assertJsonPath('data.invitation.hasSubmitted', $hasSubmitted)
                ->assertJsonPath('data.invitation.isLocked', $isLocked);
        }
    }

    public function test_all_inaccessible_tokens_return_the_same_generic_404_without_tracking(): void
    {
        $wedding = Wedding::factory()->create(['status' => Wedding::STATUS_PUBLISHED]);
        $draftToken = str_repeat('D', 43);
        $archivedToken = str_repeat('A', 43);
        $draft = Invitation::factory()->for($wedding)->create([
            'status' => Invitation::STATUS_DRAFT,
            'token_hash' => hash('sha256', $draftToken),
        ]);
        $archived = Invitation::factory()->for($wedding)->create([
            'status' => Invitation::STATUS_ARCHIVED,
            'token_hash' => hash('sha256', $archivedToken),
        ]);

        foreach ([
            $draftToken,
            $archivedToken,
            str_repeat('X', 43),
            'malformed token!',
            'short',
        ] as $token) {
            $this->getJson('/api/invitations/'.rawurlencode($token))
                ->assertNotFound()
                ->assertExactJson(['message' => 'Invitation not found.']);
        }

        $this->assertNull($draft->fresh()->first_opened_at);
        $this->assertNull($archived->fresh()->last_opened_at);
    }

    public function test_invitation_from_another_wedding_is_not_exposed(): void
    {
        Wedding::factory()->create(['status' => Wedding::STATUS_PUBLISHED]);
        $otherWedding = Wedding::factory()->create(['status' => Wedding::STATUS_PUBLISHED]);
        $token = str_repeat('O', 43);
        Invitation::factory()->for($otherWedding)->ready()->create([
            'token_hash' => hash('sha256', $token),
        ]);

        $this->getJson("/api/invitations/{$token}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Invitation not found.']);
    }

    public function test_wedding_must_exist_and_be_published(): void
    {
        $this->getJson('/api/invitations/'.str_repeat('N', 43))->assertNotFound();

        foreach ([Wedding::STATUS_DRAFT, Wedding::STATUS_ARCHIVED] as $status) {
            $wedding = Wedding::factory()->create(['status' => $status]);
            $token = str_repeat($status === Wedding::STATUS_DRAFT ? 'D' : 'R', 43);
            $invitation = Invitation::factory()->for($wedding)->ready()->create([
                'token_hash' => hash('sha256', $token),
            ]);

            $this->getJson("/api/invitations/{$token}")->assertNotFound();
            $this->assertNull($invitation->fresh()->first_opened_at);
            $wedding->delete();
        }
    }

    public function test_open_tracking_sets_once_and_advances_last_open(): void
    {
        [, $invitation, $token] = $this->accessibleInvitation();
        Guest::factory()->for($invitation)->create();

        $this->travelTo('2026-08-05 14:30:00');
        $this->getJson("/api/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath(
                'data.invitation.firstOpenedAt',
                '2026-08-05T14:30:00+00:00',
            )
            ->assertJsonPath(
                'data.invitation.lastOpenedAt',
                '2026-08-05T14:30:00+00:00',
            );

        $openedInvitation = $invitation->fresh();
        $firstOpenedAt = $openedInvitation->first_opened_at;
        $this->assertTrue($firstOpenedAt->equalTo($openedInvitation->last_opened_at));

        $this->travelTo('2026-08-06 09:15:00');
        $this->getJson("/api/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath(
                'data.invitation.firstOpenedAt',
                '2026-08-05T14:30:00+00:00',
            )
            ->assertJsonPath(
                'data.invitation.lastOpenedAt',
                '2026-08-06T09:15:00+00:00',
            );

        $invitation->refresh();
        $this->assertTrue($firstOpenedAt->equalTo($invitation->first_opened_at));
        $this->assertTrue($invitation->last_opened_at->greaterThan($firstOpenedAt));
    }

    public function test_historical_timestamps_drive_resilient_derived_flags(): void
    {
        [$wedding, $invitation, $token] = $this->accessibleInvitation();
        $wedding->update(['rsvp_deadline' => null]);
        $invitation->update([
            'status' => Invitation::STATUS_READY,
            'submitted_at' => now(),
            'locked_at' => now(),
        ]);
        Guest::factory()->for($invitation)->declined()->create();

        $this->getJson("/api/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.invitation.hasSubmitted', true)
            ->assertJsonPath('data.invitation.isLocked', true)
            ->assertJsonPath('data.invitation.canRespond', false)
            ->assertJsonPath('data.invitation.guests.0.attendanceStatus', Guest::ATTENDANCE_DECLINED);
    }

    private function accessibleInvitation(): array
    {
        $wedding = Wedding::factory()->create([
            'status' => Wedding::STATUS_PUBLISHED,
            'rsvp_deadline' => now()->addMonth()->toDateString(),
        ]);
        $token = str_repeat('T', 43);
        $invitation = Invitation::factory()->for($wedding)->ready()->create([
            'token_hash' => hash('sha256', $token),
            'contact_person_name' => 'Private Person',
            'contact_number' => '09170000000',
            'email' => 'private@example.test',
            'internal_notes' => 'Private note',
        ]);

        return [$wedding, $invitation, $token];
    }
}
