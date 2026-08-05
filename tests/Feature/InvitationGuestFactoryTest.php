<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationGuestFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_factory_creates_unique_sha256_hashes_for_a_wedding(): void
    {
        $wedding = Wedding::factory()->create();
        $invitations = Invitation::factory()->count(3)->for($wedding)->create();

        $this->assertTrue($invitations->every(
            fn (Invitation $invitation): bool => $invitation->wedding->is($wedding)
                && preg_match('/\A[a-f0-9]{64}\z/', $invitation->token_hash) === 1
        ));
        $this->assertCount(3, $invitations->pluck('token_hash')->unique());
    }

    public function test_invitation_factory_states_work_as_intended(): void
    {
        $ready = Invitation::factory()->ready()->create();
        $submitted = Invitation::factory()->submitted()->create();
        $locked = Invitation::factory()->locked()->create();

        $this->assertSame(Invitation::STATUS_READY, $ready->status);
        $this->assertSame(Invitation::STATUS_SUBMITTED, $submitted->status);
        $this->assertNotNull($submitted->submitted_at);
        $this->assertSame(Invitation::STATUS_LOCKED, $locked->status);
        $this->assertNotNull($locked->locked_at);
    }

    public function test_guest_factory_and_states_work_as_intended(): void
    {
        $invitation = Invitation::factory()->create();
        $guest = Guest::factory()->for($invitation)->create();
        $child = Guest::factory()->child()->create();
        $attending = Guest::factory()->attending()->create();
        $declined = Guest::factory()->declined()->create();

        $this->assertTrue($guest->invitation->is($invitation));
        $this->assertSame(Guest::TYPE_ADULT, $guest->guest_type);
        $this->assertSame(Guest::ATTENDANCE_PENDING, $guest->attendance_status);
        $this->assertSame(Guest::TYPE_CHILD, $child->guest_type);
        $this->assertSame(Guest::ATTENDANCE_ATTENDING, $attending->attendance_status);
        $this->assertSame(Guest::ATTENDANCE_DECLINED, $declined->attendance_status);
    }
}
