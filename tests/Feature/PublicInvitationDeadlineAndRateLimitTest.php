<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PublicInvitationDeadlineAndRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_deadline_is_inclusive_and_never_hides_or_mutates_invitation(): void
    {
        $this->travelTo('2026-11-22 18:00:00');
        $wedding = Wedding::factory()->create([
            'status' => Wedding::STATUS_PUBLISHED,
            'rsvp_deadline' => '2026-11-22',
        ]);
        $token = str_repeat('L', 43);
        $invitation = Invitation::factory()->for($wedding)->ready()->create([
            'token_hash' => hash('sha256', $token),
        ]);
        Guest::factory()->for($invitation)->create();

        $this->getJson("/api/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.invitation.canRespond', true);

        $this->travelTo('2026-11-23 00:01:00');
        $this->getJson("/api/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.invitation.canRespond', false);
        $this->assertSame(Invitation::STATUS_READY, $invitation->fresh()->status);

        $wedding->update(['rsvp_deadline' => null]);
        $this->getJson("/api/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.invitation.canRespond', true);
    }

    public function test_before_deadline_allows_response(): void
    {
        $this->travelTo('2026-11-20 12:00:00');
        $wedding = Wedding::factory()->create([
            'status' => Wedding::STATUS_PUBLISHED,
            'rsvp_deadline' => '2026-11-22',
        ]);
        $token = str_repeat('B', 43);
        $invitation = Invitation::factory()->for($wedding)->ready()->create([
            'token_hash' => hash('sha256', $token),
        ]);

        $this->getJson("/api/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.invitation.canRespond', true);
    }

    public function test_public_invitation_endpoint_is_rate_limited_by_ip(): void
    {
        $wedding = Wedding::factory()->create(['status' => Wedding::STATUS_PUBLISHED]);
        $token = str_repeat('Q', 43);
        Invitation::factory()->for($wedding)->ready()->create([
            'token_hash' => hash('sha256', $token),
        ]);
        RateLimiter::clear('127.0.0.1');

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->getJson("/api/invitations/{$token}")->assertOk();
        }

        $this->getJson("/api/invitations/{$token}")->assertTooManyRequests();
    }
}
