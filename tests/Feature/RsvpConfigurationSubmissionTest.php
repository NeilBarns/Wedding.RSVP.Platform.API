<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Wedding;
use App\Services\RsvpConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RsvpConfigurationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_invitation_includes_effective_configuration_and_invalid_token_is_unchanged(): void
    {
        [, $token] = $this->household();

        $this->getJson("/api/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.rsvpConfiguration.guestQuestions.0.key', 'attendance')
            ->assertJsonPath('data.rsvpConfiguration.guestQuestions.0.system', true)
            ->assertJsonPath('data.rsvpConfiguration.guestQuestions.1.key', 'dietaryRequirements')
            ->assertJsonPath('data.rsvpConfiguration.householdQuestions.0.key', 'responsePhone');

        $this->getJson('/api/invitations/'.str_repeat('X', 43))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Invitation not found.']);
    }

    public function test_required_guest_questions_apply_only_to_attending_guests(): void
    {
        [$invitation, $token, $attending, $declined] = $this->household();
        $this->configure($invitation->wedding, [
            'dietaryRequirements' => ['required' => true],
            'accessibilityNeeds' => ['required' => true],
        ]);

        $this->putJson("/api/invitations/{$token}/rsvp", [
            'guests' => [
                ['id' => $attending->id, 'attendanceStatus' => 'attending'],
                ['id' => $declined->id, 'attendanceStatus' => 'declined'],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'guests.0.dietaryRequirements',
            'guests.0.accessibilityRequirements',
        ])->assertJsonMissingValidationErrors([
            'guests.1.dietaryRequirements',
            'guests.1.accessibilityRequirements',
        ]);

        $this->putJson("/api/invitations/{$token}/rsvp", [
            'guests' => [
                [
                    'id' => $attending->id,
                    'attendanceStatus' => 'attending',
                    'dietaryRequirements' => 'Vegetarian',
                    'accessibilityRequirements' => 'Step-free access',
                ],
                [
                    'id' => $declined->id,
                    'attendanceStatus' => 'declined',
                    'dietaryRequirements' => 'Ignored',
                    'accessibilityRequirements' => 'Ignored',
                ],
            ],
        ])->assertOk();

        $this->assertSame('Vegetarian', $attending->fresh()->dietary_requirements);
        $this->assertNull($declined->fresh()->dietary_requirements);
        $this->assertNull($declined->fresh()->accessibility_requirements);
    }

    public function test_required_household_questions_are_enforced_with_existing_email_validation(): void
    {
        [$invitation, $token, $first, $second] = $this->household();
        $this->configure($invitation->wedding, [
            'responsePhone' => ['required' => true],
            'responseEmail' => ['required' => true],
            'messageToCouple' => ['required' => true],
        ]);
        $guests = $this->guestResponses($first, $second);

        $this->putJson("/api/invitations/{$token}/rsvp", ['guests' => $guests])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contactNumber', 'email', 'message']);

        $this->putJson("/api/invitations/{$token}/rsvp", [
            'guests' => $guests,
            'contactNumber' => '09170000000',
            'email' => 'invalid',
            'message' => 'See you there',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->putJson("/api/invitations/{$token}/rsvp", [
            'guests' => $guests,
            'contactNumber' => '09170000000',
            'email' => 'HOUSEHOLD@EXAMPLE.COM',
            'message' => 'See you there',
        ])->assertOk()->assertJsonPath('data.invitation.responseEmail', 'household@example.com');
    }

    public function test_disabled_fields_are_cleared_on_next_submission_and_revision_history_stays_immutable(): void
    {
        [$invitation, $token, $first, $second] = $this->household();
        $first->update([
            'dietary_requirements' => 'Old dietary value',
            'accessibility_requirements' => 'Old access value',
        ]);
        $invitation->update([
            'response_contact_number' => 'Old phone',
            'response_email' => 'old@example.com',
            'message_to_couple' => 'Old message',
        ]);
        $this->putJson("/api/invitations/{$token}/rsvp", [
            'guests' => $this->guestResponses($first, $second),
            'contactNumber' => 'First phone',
            'email' => 'first@example.com',
            'message' => 'First message',
        ])->assertOk();
        $firstSnapshot = $invitation->rsvpRevisions()->where('revision_number', 1)->sole()->response_snapshot;

        $this->configure($invitation->wedding, [
            'dietaryRequirements' => ['enabled' => false],
            'accessibilityNeeds' => ['enabled' => false],
            'responsePhone' => ['enabled' => false],
            'responseEmail' => ['enabled' => false],
            'messageToCouple' => ['enabled' => false],
        ]);
        $this->putJson("/api/invitations/{$token}/rsvp", [
            'guests' => [[
                'id' => $first->id,
                'attendanceStatus' => 'attending',
                'dietaryRequirements' => 'Stale dietary value',
                'accessibilityRequirements' => 'Stale access value',
            ], [
                'id' => $second->id,
                'attendanceStatus' => 'declined',
            ]],
            'contactNumber' => 'Stale phone',
            'email' => ['malformed but ignored'],
            'message' => 'Stale message',
        ])->assertOk();

        $this->assertNull($first->fresh()->dietary_requirements);
        $this->assertNull($first->fresh()->accessibility_requirements);
        $this->assertNull($invitation->fresh()->response_contact_number);
        $this->assertNull($invitation->fresh()->response_email);
        $this->assertNull($invitation->fresh()->message_to_couple);
        $this->assertSame($firstSnapshot, $invitation->rsvpRevisions()->where('revision_number', 1)->sole()->response_snapshot);
        $secondSnapshot = $invitation->rsvpRevisions()->where('revision_number', 2)->sole()->response_snapshot;
        $this->assertNull($secondSnapshot['guests'][0]['dietaryRequirements']);
        $this->assertNull($secondSnapshot['invitation']['responseEmail']);
    }

    public function test_attendance_and_exact_named_guest_set_remain_mandatory(): void
    {
        [$invitation, $token, $first, $second] = $this->household();
        $this->configure($invitation->wedding, [
            'dietaryRequirements' => ['enabled' => false],
            'accessibilityNeeds' => ['enabled' => false],
        ]);

        $this->putJson("/api/invitations/{$token}/rsvp", [
            'guests' => [['id' => $first->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors('guests.0.attendanceStatus');

        $this->putJson("/api/invitations/{$token}/rsvp", [
            'guests' => [['id' => $first->id, 'attendanceStatus' => 'attending']],
        ])->assertUnprocessable()->assertJsonValidationErrors('guests');
        $this->assertDatabaseCount('guests', 2);
        $this->assertSame(Guest::ATTENDANCE_PENDING, $second->fresh()->attendance_status);
    }

    private function household(): array
    {
        $wedding = Wedding::factory()->create([
            'status' => Wedding::STATUS_PUBLISHED,
            'rsvp_deadline' => now()->addMonth()->toDateString(),
        ]);
        $token = str_repeat('Q', 43);
        $invitation = Invitation::factory()->for($wedding)->ready()->create([
            'token_hash' => hash('sha256', $token),
        ]);
        $first = Guest::factory()->for($invitation)->create(['sort_order' => 1]);
        $second = Guest::factory()->for($invitation)->create(['sort_order' => 2]);

        return [$invitation, $token, $first, $second];
    }

    private function guestResponses(Guest $first, Guest $second): array
    {
        return [
            ['id' => $first->id, 'attendanceStatus' => 'attending'],
            ['id' => $second->id, 'attendanceStatus' => 'declined'],
        ];
    }

    private function configure(Wedding $wedding, array $overrides): void
    {
        $questions = collect([
            ['key' => 'dietaryRequirements', 'enabled' => true, 'required' => false, 'label' => 'Dietary requirements', 'helperText' => null, 'sortOrder' => 10],
            ['key' => 'accessibilityNeeds', 'enabled' => true, 'required' => false, 'label' => 'Accessibility needs', 'helperText' => null, 'sortOrder' => 20],
            ['key' => 'responsePhone', 'enabled' => true, 'required' => false, 'label' => 'Contact number', 'helperText' => null, 'sortOrder' => 10],
            ['key' => 'responseEmail', 'enabled' => true, 'required' => false, 'label' => 'Email address', 'helperText' => null, 'sortOrder' => 20],
            ['key' => 'messageToCouple', 'enabled' => true, 'required' => false, 'label' => 'Message to the couple', 'helperText' => null, 'sortOrder' => 30],
        ])->map(fn (array $question) => array_replace($question, $overrides[$question['key']] ?? []))->all();

        app(RsvpConfigurationService::class)->replace($wedding, $questions);
    }
}
