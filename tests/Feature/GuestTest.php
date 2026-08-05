<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_has_defaults_nullable_fields_and_relationships(): void
    {
        $invitation = Invitation::factory()->create();
        $guest = Guest::query()->create([
            'invitation_id' => $invitation->id,
            'full_name' => 'Maria Santos',
        ])->refresh();

        $this->assertTrue($guest->invitation->is($invitation));
        $this->assertSame(Guest::TYPE_ADULT, $guest->guest_type);
        $this->assertSame(Guest::ATTENDANCE_PENDING, $guest->attendance_status);
        $this->assertSame(0, $guest->sort_order);
        $this->assertNull($guest->dietary_requirements);
        $this->assertNull($guest->accessibility_requirements);

        Guest::factory()->for($invitation)->create();
        $this->assertCount(2, $invitation->guests);
    }

    public function test_deleting_an_invitation_cascades_to_its_guests(): void
    {
        $invitation = Invitation::factory()->create();
        $guest = Guest::factory()->for($invitation)->create();

        $invitation->delete();

        $this->assertDatabaseMissing('guests', ['id' => $guest->id]);
    }
}
