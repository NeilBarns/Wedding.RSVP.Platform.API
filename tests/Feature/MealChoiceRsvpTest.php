<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealChoiceRsvpTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_stable_meal_options(): void
    {
        $wedding = Wedding::factory()->create();
        $this->actingAs(User::factory()->create());

        $response = $this->putJson('/api/admin/rsvp-configuration', $this->payload(true, true))->assertOk();
        $meal = collect($response->json('data.questions'))->firstWhere('key', 'mealChoice');

        $this->assertSame('singleChoice', $meal['type']);
        $this->assertCount(3, $meal['options']);
        $this->assertNotNull($meal['options'][0]['id']);
        $this->assertDatabaseHas('wedding_rsvp_question_options', [
            'wedding_id' => $wedding->id,
            'value' => 'chicken',
            'enabled' => true,
        ]);

        $options = collect($meal['options'])->keyBy('value');
        $payload = $this->payload(true, true, [
            ['id' => $options['fish']['id'], 'label' => 'Market fish', 'value' => 'fish', 'sortOrder' => 5, 'enabled' => true],
            ['id' => $options['chicken']['id'], 'label' => 'Roast chicken', 'value' => 'chicken', 'sortOrder' => 10, 'enabled' => true],
        ]);
        $this->putJson('/api/admin/rsvp-configuration', $payload)->assertOk();

        $this->assertDatabaseHas('wedding_rsvp_question_options', ['value' => 'fish', 'label' => 'Market fish', 'sort_order' => 5]);
        $this->assertDatabaseHas('wedding_rsvp_question_options', ['value' => 'vegetarian', 'enabled' => false]);
        $this->assertDatabaseCount('wedding_rsvp_question_options', 3);
    }

    public function test_admin_rejects_invalid_meal_option_configuration_and_immutable_value_changes(): void
    {
        Wedding::factory()->create();
        $this->actingAs(User::factory()->create());

        $tooFew = $this->payload(true, false, [
            ['label' => 'Chicken', 'value' => 'chicken', 'sortOrder' => 10, 'enabled' => true],
            ['label' => 'Fish', 'value' => 'fish', 'sortOrder' => 20, 'enabled' => false],
        ]);
        $this->putJson('/api/admin/rsvp-configuration', $tooFew)
            ->assertUnprocessable()->assertJsonValidationErrors('questions.2.options');

        $duplicate = $this->payload(true, false, [
            ['label' => 'Chicken', 'value' => ' CHICKEN ', 'sortOrder' => 10, 'enabled' => true],
            ['label' => 'Other chicken', 'value' => 'chicken', 'sortOrder' => 20, 'enabled' => true],
        ]);
        $this->putJson('/api/admin/rsvp-configuration', $duplicate)
            ->assertUnprocessable()->assertJsonValidationErrors('questions.2.options.1.value');

        $blankLabel = $this->payload(false, false);
        $blankLabel['questions'][2]['options'][0]['label'] = '   ';
        $this->putJson('/api/admin/rsvp-configuration', $blankLabel)
            ->assertUnprocessable()->assertJsonValidationErrors('questions.2.options.0.label');

        $blankValue = $this->payload(false, false);
        $blankValue['questions'][2]['options'][0]['value'] = '   ';
        $this->putJson('/api/admin/rsvp-configuration', $blankValue)
            ->assertUnprocessable()->assertJsonValidationErrors('questions.2.options.0.value');

        $created = $this->putJson('/api/admin/rsvp-configuration', $this->payload(true, false))->assertOk();
        $meal = collect($created->json('data.questions'))->firstWhere('key', 'mealChoice');
        $changed = $this->payload(true, false, [[
            'id' => $meal['options'][0]['id'], 'label' => 'Chicken', 'value' => 'turkey', 'sortOrder' => 10, 'enabled' => true,
        ], [
            'id' => $meal['options'][1]['id'], 'label' => 'Fish', 'value' => 'fish', 'sortOrder' => 20, 'enabled' => true,
        ]]);
        $this->putJson('/api/admin/rsvp-configuration', $changed)
            ->assertUnprocessable()->assertJsonValidationErrors('questions.2.options.0.value');
    }

    public function test_public_contract_only_exposes_enabled_options_without_internal_ids(): void
    {
        [, $token] = $this->household();
        $this->actingAs(User::factory()->create());
        $this->putJson('/api/admin/rsvp-configuration', $this->payload(true, false))->assertOk();
        $response = $this->getJson("/api/invitations/{$token}")->assertOk();
        $meal = collect($response->json('data.rsvpConfiguration.guestQuestions'))->firstWhere('key', 'mealChoice');

        $this->assertSame(['Chicken', 'Fish'], collect($meal['options'])->pluck('label')->all());
        $this->assertSame(['chicken', 'fish'], collect($meal['options'])->pluck('value')->all());
        $this->assertArrayNotHasKey('id', $meal['options'][0]);
        $this->assertArrayNotHasKey('enabled', $meal['options'][0]);
    }

    public function test_submission_enforces_persists_clears_and_snapshots_meal_choice(): void
    {
        [$invitation, $token, $first, $second] = $this->household();
        $this->actingAs(User::factory()->create());
        $this->putJson('/api/admin/rsvp-configuration', $this->payload(true, true))->assertOk();
        $base = ['guests' => [
            ['id' => $first->id, 'attendanceStatus' => 'attending'],
            ['id' => $second->id, 'attendanceStatus' => 'declined'],
        ]];
        $this->putJson("/api/invitations/{$token}/rsvp", $base)
            ->assertUnprocessable()->assertJsonValidationErrors('guests.0.mealChoice');

        $invalid = $base;
        $invalid['guests'][0]['mealChoice'] = 'vegetarian';
        $this->putJson("/api/invitations/{$token}/rsvp", $invalid)
            ->assertUnprocessable()->assertJsonValidationErrors('guests.0.mealChoice');

        $valid = $base;
        $valid['guests'][0]['mealChoice'] = 'chicken';
        $valid['guests'][1]['mealChoice'] = 'fish';
        $this->putJson("/api/invitations/{$token}/rsvp", $valid)->assertOk();
        $this->assertSame('chicken', $first->fresh()->meal_choice);
        $this->assertNull($second->fresh()->meal_choice);
        $firstSnapshot = $invitation->rsvpRevisions()->where('revision_number', 1)->sole()->response_snapshot;
        $this->assertSame('chicken', $firstSnapshot['guests'][0]['mealChoice']);

        $declined = ['guests' => [
            ['id' => $first->id, 'attendanceStatus' => 'declined', 'mealChoice' => 'chicken'],
            ['id' => $second->id, 'attendanceStatus' => 'attending', 'mealChoice' => 'fish'],
        ]];
        $this->putJson("/api/invitations/{$token}/rsvp", $declined)->assertOk();
        $this->assertNull($first->fresh()->meal_choice);
        $this->assertSame('fish', $second->fresh()->meal_choice);

        $this->actingAs(User::factory()->create());
        $this->putJson('/api/admin/rsvp-configuration', $this->payload(false, false))->assertOk();
        $this->putJson("/api/invitations/{$token}/rsvp", $valid)->assertOk();
        $this->assertNull($first->fresh()->meal_choice);
        $this->assertSame($firstSnapshot, $invitation->rsvpRevisions()->where('revision_number', 1)->sole()->response_snapshot);
        $this->assertNull($invitation->rsvpRevisions()->where('revision_number', 3)->sole()->response_snapshot['guests'][0]['mealChoice']);
    }

    public function test_optional_meal_choice_allows_attending_guest_to_leave_it_blank(): void
    {
        [$invitation, $token, $first, $second] = $this->household();
        $this->actingAs(User::factory()->create());
        $this->putJson('/api/admin/rsvp-configuration', $this->payload(true, false))->assertOk();

        $this->putJson("/api/invitations/{$token}/rsvp", ['guests' => [
            ['id' => $first->id, 'attendanceStatus' => 'attending', 'mealChoice' => null],
            ['id' => $second->id, 'attendanceStatus' => 'declined'],
        ]])->assertOk();

        $this->assertNull($first->fresh()->meal_choice);
        $this->assertNull($invitation->rsvpRevisions()->sole()->response_snapshot['guests'][0]['mealChoice']);
    }

    private function payload(bool $enabled, bool $required, ?array $options = null): array
    {
        $options ??= [
            ['label' => 'Chicken', 'value' => 'chicken', 'sortOrder' => 10, 'enabled' => true],
            ['label' => 'Fish', 'value' => 'fish', 'sortOrder' => 20, 'enabled' => true],
            ['label' => 'Vegetarian', 'value' => 'vegetarian', 'sortOrder' => 30, 'enabled' => false],
        ];

        return ['questions' => [
            ['key' => 'dietaryRequirements', 'enabled' => true, 'required' => false, 'label' => 'Dietary requirements', 'helperText' => null, 'sortOrder' => 10],
            ['key' => 'accessibilityNeeds', 'enabled' => true, 'required' => false, 'label' => 'Accessibility needs', 'helperText' => null, 'sortOrder' => 20],
            ['key' => 'mealChoice', 'enabled' => $enabled, 'required' => $required, 'label' => 'Meal choice', 'helperText' => null, 'sortOrder' => 30, 'options' => $options],
            ['key' => 'responsePhone', 'enabled' => true, 'required' => false, 'label' => 'Contact number', 'helperText' => null, 'sortOrder' => 10],
            ['key' => 'responseEmail', 'enabled' => true, 'required' => false, 'label' => 'Email address', 'helperText' => null, 'sortOrder' => 20],
            ['key' => 'messageToCouple', 'enabled' => true, 'required' => false, 'label' => 'Message to the couple', 'helperText' => null, 'sortOrder' => 30],
        ]];
    }

    private function household(): array
    {
        $wedding = Wedding::factory()->create(['status' => Wedding::STATUS_PUBLISHED, 'rsvp_deadline' => now()->addMonth()->toDateString()]);
        $token = str_repeat('M', 43);
        $invitation = Invitation::factory()->for($wedding)->ready()->create(['token_hash' => hash('sha256', $token)]);
        $first = Guest::factory()->for($invitation)->create(['sort_order' => 1]);
        $second = Guest::factory()->for($invitation)->create(['sort_order' => 2]);

        return [$invitation, $token, $first, $second];
    }
}
