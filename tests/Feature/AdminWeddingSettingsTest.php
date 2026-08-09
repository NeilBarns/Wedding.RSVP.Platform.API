<?php

namespace Tests\Feature;

use App\Enums\WeddingTemplateKey;
use App\Models\User;
use App\Models\Wedding;
use App\Models\WeddingHeroContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWeddingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_administrator_can_read_wedding_settings(): void
    {
        $wedding = Wedding::factory()->create();

        foreach ([User::ROLE_OWNER, User::ROLE_ADMINISTRATOR] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson('/api/admin/wedding')
                ->assertOk()
                ->assertJsonPath('data.id', $wedding->id)
                ->assertJsonPath('data.partnerOneName', $wedding->partner_one_name)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'partnerOneName',
                        'partnerTwoName',
                        'weddingDate',
                        'rsvpDeadline',
                        'dressCode',
                        'status',
                        'templateKey',
                        'theme' => [
                            'key',
                            'primaryColor',
                            'secondaryColor',
                            'accentColor',
                            'backgroundColor',
                            'headingFont',
                            'bodyFont',
                        ],
                    ],
                ]);
        }
    }

    public function test_admin_read_requires_authentication_and_returns_404_without_a_wedding(): void
    {
        $this->getJson('/api/admin/wedding')->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/wedding')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Wedding not found.']);
    }

    public function test_owner_can_update_all_settings_and_public_endpoint_reflects_normalized_values(): void
    {
        $wedding = Wedding::factory()->create();
        $payload = $this->validPayload([
            'partnerOneName' => '  Neil Michael Barnedo  ',
            'partnerTwoName' => '  Hazel Elago ',
            'rsvpDeadline' => '2026-11-22',
            'templateKey' => WeddingTemplateKey::EditorialLinenV1->value,
            'theme' => [
                'key' => ' editorial-linen ',
                'primaryColor' => '#4a4038',
                'secondaryColor' => '#d8c9b8',
                'accentColor' => '#a38767',
                'backgroundColor' => '#f5f1ea',
                'headingFont' => ' Cormorant Garamond ',
                'bodyFont' => ' Inter ',
            ],
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))
            ->putJson('/api/admin/wedding', $payload)
            ->assertOk()
            ->assertJsonPath('data.partnerOneName', 'Neil Michael Barnedo')
            ->assertJsonPath('data.rsvpDeadline', '2026-11-22')
            ->assertJsonPath('data.theme.primaryColor', '#4A4038')
            ->assertJsonPath('data.theme.backgroundColor', '#F5F1EA');

        $this->assertDatabaseHas('weddings', [
            'id' => $wedding->id,
            'partner_one_name' => 'Neil Michael Barnedo',
            'partner_two_name' => 'Hazel Elago',
            'wedding_date' => '2026-12-22',
            'rsvp_deadline' => '2026-11-22',
            'dress_code' => 'Filipiniana',
            'status' => Wedding::STATUS_DRAFT,
            'template_key' => WeddingTemplateKey::EditorialLinenV1->value,
            'theme_key' => 'editorial-linen',
            'primary_color' => '#4A4038',
            'secondary_color' => '#D8C9B8',
            'accent_color' => '#A38767',
            'background_color' => '#F5F1EA',
            'heading_font' => 'Cormorant Garamond',
            'body_font' => 'Inter',
        ]);

        $expectedPublicData = $response->json('data');
        $expectedPublicData['content'] = [
            'hero' => null,
            'story' => [],
            'events' => [],
            'faq' => [],
            'gallery' => [],
        ];

        $this->getJson('/api/wedding')
            ->assertOk()
            ->assertJsonPath('data', $expectedPublicData);
    }

    public function test_administrator_can_update_settings_and_unauthenticated_user_cannot(): void
    {
        Wedding::factory()->create();
        $payload = $this->validPayload(['dressCode' => 'Formal']);

        $this->putJson('/api/admin/wedding', $payload)->assertUnauthorized();

        $this->actingAs(User::factory()->create([
            'role' => User::ROLE_ADMINISTRATOR,
        ]))->putJson('/api/admin/wedding', $payload)
            ->assertOk()
            ->assertJsonPath('data.dressCode', 'Formal');
    }

    public function test_required_fields_names_dates_and_status_are_validated(): void
    {
        Wedding::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->putJson('/api/admin/wedding', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'partnerOneName',
                'partnerTwoName',
                'weddingDate',
                'status',
            ]);

        foreach ([
            ['partnerOneName' => '   '],
            ['partnerTwoName' => "\t "],
            ['weddingDate' => 'December 22, 2026'],
            ['status' => 'attending'],
        ] as $invalid) {
            $this->putJson('/api/admin/wedding', $this->validPayload($invalid))
                ->assertUnprocessable();
        }
    }

    public function test_rsvp_deadline_must_be_before_the_wedding_date_or_null(): void
    {
        Wedding::factory()->create();
        $this->actingAs(User::factory()->create());

        foreach (['2026-12-22', '2026-12-23'] as $deadline) {
            $this->putJson('/api/admin/wedding', $this->validPayload([
                'rsvpDeadline' => $deadline,
            ]))->assertUnprocessable()->assertJsonValidationErrors('rsvpDeadline');
        }

        foreach (['2026-11-22', null] as $deadline) {
            $this->putJson('/api/admin/wedding', $this->validPayload([
                'rsvpDeadline' => $deadline,
            ]))->assertOk()->assertJsonPath('data.rsvpDeadline', $deadline);
        }
    }

    public function test_theme_colors_are_validated_and_normalized(): void
    {
        Wedding::factory()->create();
        $this->actingAs(User::factory()->create());

        foreach (['F5F1EA', '#FFF', 'linen'] as $color) {
            $this->putJson('/api/admin/wedding', $this->validPayload([
                'theme' => ['primaryColor' => $color],
            ]))->assertUnprocessable()->assertJsonValidationErrors('theme.primaryColor');
        }

        $this->putJson('/api/admin/wedding', $this->validPayload([
            'theme' => [
                'primaryColor' => '#4a4038',
                'key' => ' ',
                'secondaryColor' => '',
                'accentColor' => null,
                'backgroundColor' => '   ',
                'headingFont' => '',
                'bodyFont' => ' ',
            ],
        ]))->assertOk()
            ->assertJsonPath('data.theme.primaryColor', '#4A4038')
            ->assertJsonPath('data.theme.key', null)
            ->assertJsonPath('data.theme.secondaryColor', null)
            ->assertJsonPath('data.theme.accentColor', null)
            ->assertJsonPath('data.theme.backgroundColor', null)
            ->assertJsonPath('data.theme.headingFont', null)
            ->assertJsonPath('data.theme.bodyFont', null);
    }

    public function test_only_supported_template_keys_are_accepted(): void
    {
        Wedding::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->putJson('/api/admin/wedding', $this->validPayload([
            'templateKey' => WeddingTemplateKey::EditorialLinenV1->value,
        ]))->assertOk()
            ->assertJsonPath('data.templateKey', WeddingTemplateKey::EditorialLinenV1->value);

        $this->putJson('/api/admin/wedding', $this->validPayload([
            'templateKey' => WeddingTemplateKey::ModernMinimalV1->value,
        ]))->assertOk()
            ->assertJsonPath('data.templateKey', WeddingTemplateKey::ModernMinimalV1->value);

        foreach (['unknown', 'classic', '<script>'] as $templateKey) {
            $this->putJson('/api/admin/wedding', $this->validPayload([
                'templateKey' => $templateKey,
            ]))->assertUnprocessable()->assertJsonValidationErrors('templateKey');
        }
    }

    public function test_template_enum_contains_both_built_in_template_keys(): void
    {
        $this->assertSame([
            'editorial-linen-v1',
            'modern-minimal-v1',
        ], array_column(WeddingTemplateKey::cases(), 'value'));
    }

    public function test_modern_minimal_persists_publicly_and_can_switch_back_without_changing_theme_or_content(): void
    {
        $wedding = Wedding::factory()->create([
            'status' => Wedding::STATUS_PUBLISHED,
            'template_key' => WeddingTemplateKey::EditorialLinenV1->value,
            'theme_key' => 'custom-theme',
            'primary_color' => '#112233',
            'secondary_color' => '#445566',
            'accent_color' => '#778899',
            'background_color' => '#AABBCC',
            'heading_font' => 'Georgia',
            'body_font' => 'Inter',
        ]);
        WeddingHeroContent::factory()->for($wedding)->published()->create([
            'headline' => 'Persisted hero',
        ]);
        $this->actingAs(User::factory()->create());

        $theme = [
            'key' => 'custom-theme',
            'primaryColor' => '#112233',
            'secondaryColor' => '#445566',
            'accentColor' => '#778899',
            'backgroundColor' => '#AABBCC',
            'headingFont' => 'Georgia',
            'bodyFont' => 'Inter',
        ];

        $this->putJson('/api/admin/wedding', $this->validPayload([
            'status' => Wedding::STATUS_PUBLISHED,
            'templateKey' => WeddingTemplateKey::ModernMinimalV1->value,
            'theme' => $theme,
        ]))->assertOk()
            ->assertJsonPath('data.templateKey', WeddingTemplateKey::ModernMinimalV1->value)
            ->assertJsonPath('data.theme', $theme);

        $this->getJson('/api/admin/wedding')->assertOk()
            ->assertJsonPath('data.templateKey', WeddingTemplateKey::ModernMinimalV1->value);
        $this->getJson('/api/wedding')->assertOk()
            ->assertJsonPath('data.templateKey', WeddingTemplateKey::ModernMinimalV1->value)
            ->assertJsonPath('data.theme', $theme)
            ->assertJsonPath('data.content.hero.headline', 'Persisted hero');

        $this->putJson('/api/admin/wedding', $this->validPayload([
            'status' => Wedding::STATUS_PUBLISHED,
            'templateKey' => WeddingTemplateKey::EditorialLinenV1->value,
            'theme' => $theme,
        ]))->assertOk()
            ->assertJsonPath('data.templateKey', WeddingTemplateKey::EditorialLinenV1->value)
            ->assertJsonPath('data.theme', $theme);

        $wedding->refresh();
        $this->assertSame(WeddingTemplateKey::EditorialLinenV1->value, $wedding->template_key);
        $this->assertSame('custom-theme', $wedding->theme_key);
        $this->assertSame('#112233', $wedding->primary_color);
        $this->assertSame(1, $wedding->heroContent()->count());
        $this->assertSame('Persisted hero', $wedding->heroContent()->value('headline'));
    }

    public function test_older_put_payload_and_theme_changes_preserve_template_identity(): void
    {
        $wedding = Wedding::factory()->create([
            'template_key' => WeddingTemplateKey::EditorialLinenV1->value,
            'theme_key' => 'original-theme',
            'primary_color' => '#111111',
        ]);
        $this->actingAs(User::factory()->create());

        $this->putJson('/api/admin/wedding', $this->validPayload([
            'dressCode' => 'Cocktail',
            'theme' => [
                'key' => 'updated-theme',
                'primaryColor' => '#abcdef',
            ],
        ]))->assertOk()
            ->assertJsonPath('data.templateKey', WeddingTemplateKey::EditorialLinenV1->value)
            ->assertJsonPath('data.theme.key', 'updated-theme')
            ->assertJsonPath('data.theme.primaryColor', '#ABCDEF');

        $wedding->refresh();

        $this->assertSame(WeddingTemplateKey::EditorialLinenV1->value, $wedding->template_key);
        $this->assertSame('updated-theme', $wedding->theme_key);
        $this->assertSame('#ABCDEF', $wedding->primary_color);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'partnerOneName' => 'Neil Michael Barnedo',
            'partnerTwoName' => 'Hazel Elago',
            'weddingDate' => '2026-12-22',
            'rsvpDeadline' => null,
            'dressCode' => 'Filipiniana',
            'status' => Wedding::STATUS_DRAFT,
            'theme' => [
                'key' => null,
                'primaryColor' => null,
                'secondaryColor' => null,
                'accentColor' => null,
                'backgroundColor' => null,
                'headingFont' => null,
                'bodyFont' => null,
            ],
        ], $overrides);
    }
}
