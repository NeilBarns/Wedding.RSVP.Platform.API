<?php

namespace Tests\Feature;

use App\Enums\RsvpQuestionKey;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRsvpConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_configuration_is_complete_scoped_and_has_fixed_attendance(): void
    {
        Wedding::factory()->create();
        $this->actingAs(User::factory()->create());

        $questions = $this->getJson('/api/admin/rsvp-configuration')
            ->assertOk()
            ->json('data.questions');

        $this->assertSame([
            'accessibilityNeeds',
            'attendance',
            'dietaryRequirements',
            'mealChoice',
            'messageToCouple',
            'responseEmail',
            'responsePhone',
        ], collect($questions)->pluck('key')->sort()->values()->all());
        $this->assertSame(['attendance', 'dietaryRequirements', 'accessibilityNeeds', 'mealChoice'], collect($questions)->where('scope', 'guest')->pluck('key')->values()->all());
        $this->assertSame(['responsePhone', 'responseEmail', 'messageToCouple'], collect($questions)->where('scope', 'household')->pluck('key')->values()->all());
        $attendance = collect($questions)->firstWhere('key', 'attendance');
        $this->assertTrue($attendance['enabled']);
        $this->assertTrue($attendance['required']);
        $this->assertTrue($attendance['system']);
        $meal = collect($questions)->firstWhere('key', 'mealChoice');
        $this->assertFalse($meal['enabled']);
        $this->assertSame('singleChoice', $meal['type']);
        $this->assertSame([], $meal['options']);
        $this->assertDatabaseCount('wedding_rsvp_questions', 0);
    }

    public function test_routes_require_authentication_and_owner_and_administrator_can_use_them(): void
    {
        Wedding::factory()->create();
        $this->getJson('/api/admin/rsvp-configuration')->assertUnauthorized();
        $this->putJson('/api/admin/rsvp-configuration', $this->payload())->assertUnauthorized();

        foreach ([User::ROLE_OWNER, User::ROLE_ADMINISTRATOR] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/api/admin/rsvp-configuration')->assertOk();
        }
    }

    public function test_complete_update_is_normalized_persisted_and_server_owns_scope(): void
    {
        $wedding = Wedding::factory()->create();
        $this->actingAs(User::factory()->create());
        $payload = $this->payload();
        $payload['questions'][0] = [
            ...$payload['questions'][0],
            'enabled' => true,
            'required' => true,
            'label' => '  Any dietary requirements?  ',
            'helperText' => '  Include allergies.  ',
            'scope' => 'household',
        ];

        unset($payload['questions'][0]['scope']);
        $this->putJson('/api/admin/rsvp-configuration', $payload)
            ->assertOk()
            ->assertJsonPath('data.questions.1.key', 'dietaryRequirements')
            ->assertJsonPath('data.questions.1.scope', 'guest')
            ->assertJsonPath('data.questions.1.required', true)
            ->assertJsonPath('data.questions.1.label', 'Any dietary requirements?')
            ->assertJsonPath('data.questions.1.helperText', 'Include allergies.');

        $this->assertDatabaseCount('wedding_rsvp_questions', 6);
        $this->assertDatabaseHas('wedding_rsvp_questions', [
            'wedding_id' => $wedding->id,
            'key' => RsvpQuestionKey::DietaryRequirements->value,
            'scope' => 'guest',
            'required' => true,
        ]);
    }

    public function test_full_update_rejects_unknown_duplicate_missing_blank_and_inconsistent_questions(): void
    {
        Wedding::factory()->create();
        $this->actingAs(User::factory()->create());

        $cases = [
            ['questions.0.key', fn (array $payload) => tap($payload, fn (&$value) => $value['questions'][0]['key'] = 'unknown')],
            ['questions.1.key', fn (array $payload) => tap($payload, fn (&$value) => $value['questions'][1]['key'] = 'dietaryRequirements')],
            ['questions', fn (array $payload) => tap($payload, fn (&$value) => array_pop($value['questions']))],
            ['questions.0.label', fn (array $payload) => tap($payload, fn (&$value) => $value['questions'][0]['label'] = '   ')],
            ['questions.0.required', fn (array $payload) => tap($payload, function (&$value) {
                $value['questions'][0]['enabled'] = false;
                $value['questions'][0]['required'] = true;
            })],
            ['questions.0.key', fn (array $payload) => tap($payload, fn (&$value) => $value['questions'][0]['key'] = 'attendance')],
        ];

        foreach ($cases as [$error, $mutate]) {
            $this->putJson('/api/admin/rsvp-configuration', $mutate($this->payload()))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($error);
        }
    }

    public function test_rsvp_configuration_is_independent_from_template_theme_and_content(): void
    {
        $wedding = Wedding::factory()->create([
            'template_key' => 'modern-minimal-v1',
            'theme_key' => 'custom',
        ]);
        $this->actingAs(User::factory()->create())
            ->putJson('/api/admin/rsvp-configuration', $this->payload())
            ->assertOk();

        $this->assertSame('modern-minimal-v1', $wedding->fresh()->template_key);
        $this->assertSame('custom', $wedding->fresh()->theme_key);
        $this->assertSame(6, $wedding->rsvpQuestions()->count());

        $wedding->update(['template_key' => 'editorial-linen-v1', 'theme_key' => 'changed']);
        $this->assertSame(6, $wedding->rsvpQuestions()->count());
    }

    private function payload(): array
    {
        return ['questions' => [
            ['key' => 'dietaryRequirements', 'enabled' => true, 'required' => false, 'label' => 'Dietary requirements', 'helperText' => null, 'sortOrder' => 10],
            ['key' => 'accessibilityNeeds', 'enabled' => true, 'required' => false, 'label' => 'Accessibility needs', 'helperText' => null, 'sortOrder' => 20],
            ['key' => 'mealChoice', 'enabled' => false, 'required' => false, 'label' => 'Meal choice', 'helperText' => null, 'sortOrder' => 30, 'options' => []],
            ['key' => 'responsePhone', 'enabled' => true, 'required' => false, 'label' => 'Contact number', 'helperText' => null, 'sortOrder' => 10],
            ['key' => 'responseEmail', 'enabled' => true, 'required' => false, 'label' => 'Email address', 'helperText' => null, 'sortOrder' => 20],
            ['key' => 'messageToCouple', 'enabled' => true, 'required' => false, 'label' => 'Message to the couple', 'helperText' => null, 'sortOrder' => 30],
        ]];
    }
}
