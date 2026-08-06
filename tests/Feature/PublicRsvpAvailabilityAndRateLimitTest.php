<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRsvpAvailabilityAndRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_inaccessible_tokens_share_the_generic_404(): void
    {
        $wedding = Wedding::factory()->create(['status' => Wedding::STATUS_PUBLISHED]);

        foreach ([Invitation::STATUS_DRAFT, Invitation::STATUS_ARCHIVED] as $index => $status) {
            $token = str_repeat($index === 0 ? 'D' : 'A', 43);
            $invitation = Invitation::factory()->for($wedding)->create([
                'status' => $status,
                'token_hash' => hash('sha256', $token),
            ]);
            $guest = Guest::factory()->for($invitation)->create();

            $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($guest))
                ->assertNotFound()
                ->assertExactJson(['message' => 'Invitation not found.']);
        }

        foreach ([str_repeat('X', 43), 'malformed!'] as $token) {
            $this->putJson('/api/invitations/'.rawurlencode($token).'/rsvp', [
                'guests' => [['id' => 1, 'attendanceStatus' => Guest::ATTENDANCE_ATTENDING]],
            ])->assertNotFound()
                ->assertExactJson(['message' => 'Invitation not found.']);
        }
    }

    public function test_wrong_wedding_unpublished_and_missing_wedding_are_generic_404(): void
    {
        $current = Wedding::factory()->create(['status' => Wedding::STATUS_PUBLISHED]);
        $other = Wedding::factory()->create(['status' => Wedding::STATUS_PUBLISHED]);
        $token = str_repeat('O', 43);
        $invitation = Invitation::factory()->for($other)->ready()->create([
            'token_hash' => hash('sha256', $token),
        ]);
        $guest = Guest::factory()->for($invitation)->create();

        $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($guest))
            ->assertNotFound();

        $current->update(['status' => Wedding::STATUS_DRAFT]);
        $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($guest))
            ->assertNotFound();
        $current->update(['status' => Wedding::STATUS_ARCHIVED]);
        $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($guest))
            ->assertNotFound();

        Wedding::query()->delete();
        $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($guest))
            ->assertNotFound();
    }

    public function test_locked_or_expired_invitation_returns_conflict_without_mutation(): void
    {
        $this->travelTo('2026-11-23 00:01:00');
        [$wedding, $invitation, $token, $guest] = $this->household('2026-11-22');

        $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($guest))
            ->assertConflict()
            ->assertExactJson([
                'message' => 'This invitation is no longer accepting RSVP responses.',
            ]);

        $this->assertSame(Invitation::STATUS_READY, $invitation->fresh()->status);
        $this->assertDatabaseCount('rsvp_revisions', 0);

        $wedding->update(['rsvp_deadline' => null]);
        $invitation->update(['locked_at' => now()]);
        $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($guest))
            ->assertConflict();

        $invitation->update([
            'status' => Invitation::STATUS_LOCKED,
            'locked_at' => null,
        ]);
        $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($guest))
            ->assertConflict();

        $this->getJson("/api/invitations/{$token}")->assertOk();
    }

    public function test_deadline_is_inclusive_and_null_deadline_allows_submission(): void
    {
        $this->travelTo('2026-11-22 23:59:00');
        [$wedding, $invitation, $token, $guest] = $this->household('2026-11-22');

        $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($guest))
            ->assertOk();

        $invitation->update([
            'status' => Invitation::STATUS_READY,
            'submitted_at' => null,
        ]);
        $wedding->update(['rsvp_deadline' => null]);
        $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($guest))
            ->assertOk()
            ->assertJsonPath('data.revisionNumber', 2);
    }

    public function test_write_limiter_is_ten_per_ip_and_separate_from_read_limiter(): void
    {
        [, , $token, $guest] = $this->household(null);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($guest))
                ->assertOk()
                ->assertJsonPath('data.revisionNumber', $attempt);
        }

        $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($guest))
            ->assertTooManyRequests();

        $this->getJson("/api/invitations/{$token}")->assertOk();
    }

    public function test_rsvp_route_is_public_and_only_put_is_supported(): void
    {
        [, , $token, $guest] = $this->household(null);

        $this->putJson("/api/invitations/{$token}/rsvp", $this->payload($guest))
            ->assertOk();
        $this->postJson("/api/invitations/{$token}/rsvp", $this->payload($guest))
            ->assertMethodNotAllowed();
        $this->patchJson("/api/invitations/{$token}/rsvp", $this->payload($guest))
            ->assertMethodNotAllowed();
        $this->deleteJson("/api/invitations/{$token}/rsvp")
            ->assertMethodNotAllowed();
    }

    private function household(?string $deadline): array
    {
        $wedding = Wedding::factory()->create([
            'status' => Wedding::STATUS_PUBLISHED,
            'rsvp_deadline' => $deadline,
        ]);
        $token = str_repeat('Q', 43);
        $invitation = Invitation::factory()->for($wedding)->ready()->create([
            'token_hash' => hash('sha256', $token),
        ]);
        $guest = Guest::factory()->for($invitation)->create();

        return [$wedding, $invitation, $token, $guest];
    }

    private function payload(Guest $guest): array
    {
        return [
            'guests' => [[
                'id' => $guest->id,
                'attendanceStatus' => Guest::ATTENDANCE_ATTENDING,
            ]],
        ];
    }
}
