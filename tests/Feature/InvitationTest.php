<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Wedding;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invitation_has_defaults_nullable_fields_and_relationships(): void
    {
        $wedding = Wedding::factory()->create();
        $invitation = Invitation::query()->create([
            'wedding_id' => $wedding->id,
            'display_name' => 'The Santos Family',
            'token_hash' => hash('sha256', 'stable-test-token'),
        ])->refresh();

        $this->assertTrue($invitation->wedding->is($wedding));
        $this->assertSame(Invitation::STATUS_DRAFT, $invitation->status);
        $this->assertNull($invitation->contact_person_name);
        $this->assertNull($invitation->contact_number);
        $this->assertNull($invitation->email);
        $this->assertNull($invitation->internal_notes);

        Invitation::factory()->for($wedding)->create();
        $this->assertCount(2, $wedding->invitations);
    }

    public function test_lifecycle_timestamps_are_cast_to_datetimes(): void
    {
        $invitation = Invitation::factory()->create([
            'first_opened_at' => '2026-08-01 10:00:00',
            'last_opened_at' => '2026-08-02 11:00:00',
            'submitted_at' => '2026-08-03 12:00:00',
            'locked_at' => '2026-08-04 13:00:00',
        ]);

        foreach (['first_opened_at', 'last_opened_at', 'submitted_at', 'locked_at'] as $attribute) {
            $this->assertInstanceOf(\DateTimeInterface::class, $invitation->{$attribute});
        }
    }

    public function test_token_hash_must_be_unique(): void
    {
        $hash = hash('sha256', 'duplicate-token');
        Invitation::factory()->create(['token_hash' => $hash]);

        $this->expectException(QueryException::class);
        Invitation::factory()->create(['token_hash' => $hash]);
    }

    public function test_deleting_a_wedding_cascades_to_its_invitations(): void
    {
        $wedding = Wedding::factory()->create();
        $invitation = Invitation::factory()->for($wedding)->create();

        $wedding->delete();

        $this->assertDatabaseMissing('invitations', ['id' => $invitation->id]);
    }
}
