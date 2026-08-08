<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRsvpValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_household_must_include_exactly_each_current_guest_once(): void
    {
        [$invitation, $token, $first, $second] = $this->household();
        $outside = Guest::factory()->create();

        $cases = [
            [['id' => $first->id, 'attendanceStatus' => Guest::ATTENDANCE_ATTENDING]],
            [
                ['id' => $first->id, 'attendanceStatus' => Guest::ATTENDANCE_ATTENDING],
                ['id' => $first->id, 'attendanceStatus' => Guest::ATTENDANCE_DECLINED],
            ],
            [
                ['id' => $first->id, 'attendanceStatus' => Guest::ATTENDANCE_ATTENDING],
                ['id' => 999999, 'attendanceStatus' => Guest::ATTENDANCE_DECLINED],
            ],
            [
                ['id' => $first->id, 'attendanceStatus' => Guest::ATTENDANCE_ATTENDING],
                ['id' => $outside->id, 'attendanceStatus' => Guest::ATTENDANCE_DECLINED],
            ],
        ];

        foreach ($cases as $index => $guests) {
            $response = $this->putJson("/api/invitations/{$token}/rsvp", ['guests' => $guests])
                ->assertUnprocessable();

            if ($index === 1) {
                $response->assertJsonValidationErrors([
                    'guests.0.id',
                    'guests.1.id',
                ]);
            } else {
                $response->assertJsonValidationErrors('guests');
            }
        }

        $this->assertSame(Invitation::STATUS_READY, $invitation->fresh()->status);
        $this->assertSame(Guest::ATTENDANCE_PENDING, $first->fresh()->attendance_status);
        $this->assertSame(Guest::ATTENDANCE_PENDING, $second->fresh()->attendance_status);
        $this->assertDatabaseCount('rsvp_revisions', 0);
    }

    public function test_exact_guest_set_is_accepted_and_cannot_modify_guest_identity(): void
    {
        [, $token, $first, $second] = $this->household();

        $this->putJson("/api/invitations/{$token}/rsvp", [
            'guests' => [
                [
                    'id' => $first->id,
                    'attendanceStatus' => Guest::ATTENDANCE_ATTENDING,
                    'fullName' => 'Injected Name',
                ],
                ['id' => $second->id, 'attendanceStatus' => Guest::ATTENDANCE_DECLINED],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('guests.0');

        $this->assertSame($first->full_name, $first->fresh()->full_name);
        $this->assertDatabaseCount('guests', 2);

        $this->putJson("/api/invitations/{$token}/rsvp", [
            'guests' => [
                ['id' => $second->id, 'attendanceStatus' => Guest::ATTENDANCE_DECLINED],
                ['id' => $first->id, 'attendanceStatus' => Guest::ATTENDANCE_ATTENDING],
            ],
        ])->assertOk();
    }

    public function test_attendance_and_text_fields_are_strictly_validated_and_normalized(): void
    {
        [, $token, $first, $second] = $this->household();

        foreach ([Guest::ATTENDANCE_PENDING, 'unknown', null] as $status) {
            $this->putJson("/api/invitations/{$token}/rsvp", [
                'guests' => [
                    ['id' => $first->id, 'attendanceStatus' => $status],
                    ['id' => $second->id, 'attendanceStatus' => Guest::ATTENDANCE_DECLINED],
                ],
            ])->assertUnprocessable()
                ->assertJsonValidationErrors('guests.0.attendanceStatus');
        }

        $this->putJson("/api/invitations/{$token}/rsvp", [
            'guests' => [
                [
                    'id' => $first->id,
                    'attendanceStatus' => Guest::ATTENDANCE_ATTENDING,
                    'dietaryRequirements' => str_repeat('x', 2001),
                ],
                ['id' => $second->id, 'attendanceStatus' => Guest::ATTENDANCE_DECLINED],
            ],
            'contactNumber' => str_repeat('1', 31),
            'message' => str_repeat('m', 5001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'guests.0.dietaryRequirements',
                'contactNumber',
                'message',
            ]);

        $this->putJson("/api/invitations/{$token}/rsvp", [
            'guests' => [
                [
                    'id' => $first->id,
                    'attendanceStatus' => Guest::ATTENDANCE_ATTENDING,
                    'dietaryRequirements' => ' ',
                    'accessibilityRequirements' => "\t",
                ],
                ['id' => $second->id, 'attendanceStatus' => Guest::ATTENDANCE_DECLINED],
            ],
            'email' => 'not-an-email',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_nullable_response_fields_and_declined_note_clearing(): void
    {
        [, $token, $first, $second] = $this->household();
        $first->update([
            'attendance_status' => Guest::ATTENDANCE_ATTENDING,
            'dietary_requirements' => 'Old diet',
            'accessibility_requirements' => 'Old access',
        ]);

        $this->putJson("/api/invitations/{$token}/rsvp", [
            'guests' => [
                [
                    'id' => $first->id,
                    'attendanceStatus' => Guest::ATTENDANCE_DECLINED,
                    'dietaryRequirements' => 'Submitted but ignored',
                    'accessibilityRequirements' => 'Submitted but ignored',
                ],
                [
                    'id' => $second->id,
                    'attendanceStatus' => Guest::ATTENDANCE_ATTENDING,
                    'dietaryRequirements' => ' ',
                    'accessibilityRequirements' => '',
                ],
            ],
            'contactNumber' => ' ',
            'email' => null,
            'message' => '',
        ])->assertOk();

        $this->assertNull($first->fresh()->dietary_requirements);
        $this->assertNull($first->fresh()->accessibility_requirements);
        $this->assertNull($second->fresh()->dietary_requirements);
        $this->assertNull($second->fresh()->accessibility_requirements);
        $invitation = $first->invitation->fresh();
        $this->assertNull($invitation->response_contact_number);
        $this->assertNull($invitation->response_email);
        $this->assertNull($invitation->message_to_couple);
    }

    private function household(): array
    {
        $wedding = Wedding::factory()->create([
            'status' => Wedding::STATUS_PUBLISHED,
            'rsvp_deadline' => now()->addMonth()->toDateString(),
        ]);
        $token = str_repeat('V', 43);
        $invitation = Invitation::factory()->for($wedding)->ready()->create([
            'token_hash' => hash('sha256', $token),
        ]);
        $first = Guest::factory()->for($invitation)->create(['sort_order' => 1]);
        $second = Guest::factory()->for($invitation)->create(['sort_order' => 2]);

        return [$invitation, $token, $first, $second];
    }
}
