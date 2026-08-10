<?php

namespace Tests\Feature;

use App\Enums\RsvpQuestionKey;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\RsvpRevision;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMealChoiceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_requires_authentication_and_returns_not_found_without_a_wedding(): void
    {
        $this->getJson('/api/admin/reports/meal-choices')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/reports/meal-choices')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Wedding not found.']);
    }

    public function test_owner_and_administrator_can_access_report(): void
    {
        Wedding::factory()->create();

        foreach ([User::ROLE_OWNER, User::ROLE_ADMINISTRATOR] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/api/admin/reports/meal-choices')
                ->assertOk()
                ->assertJsonPath('data.configuration.label', 'Meal choice');
        }
    }

    public function test_report_classifies_aggregates_orders_and_excludes_non_attending_and_archived_guests(): void
    {
        $wedding = Wedding::factory()->create();
        $wedding->rsvpQuestions()->create([
            'key' => RsvpQuestionKey::MealChoice,
            'scope' => 'guest',
            'enabled' => true,
            'required' => false,
            'label' => 'Dinner selection',
            'helper_text' => null,
            'sort_order' => 30,
        ]);
        $wedding->rsvpQuestionOptions()->createMany([
            ['question_key' => RsvpQuestionKey::MealChoice, 'label' => 'Vegetarian', 'value' => 'vegetarian', 'sort_order' => 20, 'enabled' => true],
            ['question_key' => RsvpQuestionKey::MealChoice, 'label' => 'Roast Chicken', 'value' => 'roast-chicken', 'sort_order' => 10, 'enabled' => true],
            ['question_key' => RsvpQuestionKey::MealChoice, 'label' => 'Fish', 'value' => 'fish', 'sort_order' => 30, 'enabled' => false],
        ]);

        $beta = Invitation::factory()->for($wedding)->submitted()->create(['display_name' => 'Beta Family']);
        $alpha = Invitation::factory()->for($wedding)->locked()->create(['display_name' => 'Alpha Family']);
        $ready = Invitation::factory()->for($wedding)->ready()->create(['display_name' => 'Ready Family']);
        $draft = Invitation::factory()->for($wedding)->create(['display_name' => 'Draft Family']);
        $archived = Invitation::factory()->for($wedding)->create(['display_name' => 'Archived Family', 'status' => Invitation::STATUS_ARCHIVED]);

        Guest::factory()->for($beta)->attending()->create(['full_name' => 'Selected Two', 'meal_choice' => 'roast-chicken', 'sort_order' => 2]);
        Guest::factory()->for($alpha)->attending()->create(['full_name' => 'Selected One', 'meal_choice' => 'roast-chicken', 'sort_order' => 2]);
        Guest::factory()->for($alpha)->attending()->create(['full_name' => 'No Meal', 'meal_choice' => null, 'sort_order' => 1]);
        Guest::factory()->for($beta)->attending()->create(['full_name' => 'Disabled Fish', 'meal_choice' => 'fish', 'sort_order' => 1]);
        Guest::factory()->for($ready)->attending()->create(['full_name' => 'Legacy Value', 'meal_choice' => 'legacy-value']);
        Guest::factory()->for($draft)->declined()->create(['meal_choice' => 'vegetarian']);
        Guest::factory()->for($draft)->create(['attendance_status' => Guest::ATTENDANCE_PENDING, 'meal_choice' => 'vegetarian']);
        Guest::factory()->for($archived)->attending()->create(['meal_choice' => 'vegetarian']);
        $otherWedding = Wedding::factory()->create();
        Guest::factory()->for(Invitation::factory()->for($otherWedding)->submitted()->create())->attending()->create(['meal_choice' => 'roast-chicken']);

        RsvpRevision::query()->create([
            'invitation_id' => $beta->id,
            'revision_number' => 1,
            'response_snapshot' => ['historical' => true],
            'submitted_at' => now(),
        ]);
        $revision = RsvpRevision::query()->sole();

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/reports/meal-choices')
            ->assertOk()
            ->assertJsonPath('data.configuration', ['enabled' => true, 'required' => false, 'label' => 'Dinner selection'])
            ->assertJsonPath('data.summary', ['attendingGuests' => 5, 'selected' => 2, 'unselected' => 1, 'stale' => 2]);

        $this->assertSame(['roast-chicken', 'vegetarian'], collect($response->json('data.options'))->pluck('value')->all());
        $this->assertSame([2, 0], collect($response->json('data.options'))->pluck('count')->all());
        $this->assertSame(['No Meal', 'Selected One', 'Disabled Fish', 'Selected Two', 'Legacy Value'], collect($response->json('data.guests'))->pluck('guestName')->all());

        $rows = collect($response->json('data.guests'))->keyBy('guestName');
        $this->assertSame(['value' => null, 'label' => null, 'status' => 'unselected'], $rows['No Meal']['mealChoice']);
        $this->assertSame(['value' => 'fish', 'label' => 'Fish', 'status' => 'stale'], $rows['Disabled Fish']['mealChoice']);
        $this->assertSame(['value' => 'legacy-value', 'label' => null, 'status' => 'stale'], $rows['Legacy Value']['mealChoice']);
        $this->assertSame($revision->response_snapshot, $revision->fresh()->response_snapshot);
        $this->assertDatabaseCount('rsvp_revisions', 1);
    }

    public function test_disabled_question_still_classifies_values_against_enabled_option_records_and_is_independent(): void
    {
        $wedding = Wedding::factory()->create(['template_key' => 'modern-minimal-v1', 'theme_key' => 'custom']);
        $wedding->rsvpQuestionOptions()->create([
            'question_key' => RsvpQuestionKey::MealChoice,
            'label' => 'Vegetarian',
            'value' => 'vegetarian',
            'sort_order' => 10,
            'enabled' => true,
        ]);
        $invitation = Invitation::factory()->for($wedding)->locked()->create();
        Guest::factory()->for($invitation)->attending()->create(['meal_choice' => 'vegetarian']);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/reports/meal-choices')
            ->assertOk()
            ->assertJsonPath('data.configuration.enabled', false)
            ->assertJsonPath('data.summary.selected', 1)
            ->assertJsonPath('data.options.0.count', 1)
            ->assertJsonPath('data.guests.0.mealChoice.status', 'selected');

        $this->assertSame('modern-minimal-v1', $wedding->fresh()->template_key);
        $this->assertSame('custom', $wedding->fresh()->theme_key);
    }
}
