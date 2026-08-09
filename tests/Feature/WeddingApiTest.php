<?php

namespace Tests\Feature;

use App\Enums\WeddingTemplateKey;
use App\Models\Wedding;
use Database\Seeders\InitialWeddingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeddingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_public_wedding_response(): void
    {
        $this->seed(InitialWeddingSeeder::class);
        $wedding = Wedding::query()->sole();

        $this->getJson('/api/wedding')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $wedding->id,
                    'partnerOneName' => 'Neil Barnedo',
                    'partnerTwoName' => 'Hazel Elago',
                    'weddingDate' => '2026-12-22',
                    'rsvpDeadline' => null,
                    'dressCode' => 'Filipiniana',
                    'status' => Wedding::STATUS_DRAFT,
                    'templateKey' => WeddingTemplateKey::EditorialLinenV1->value,
                    'theme' => [
                        'key' => null,
                        'primaryColor' => null,
                        'secondaryColor' => null,
                        'accentColor' => null,
                        'backgroundColor' => null,
                        'headingFont' => null,
                        'bodyFont' => null,
                    ],
                    'content' => [
                        'hero' => null,
                        'story' => [],
                        'events' => [],
                        'faq' => [],
                        'gallery' => [],
                    ],
                ],
            ])
            ->assertJsonMissingPath('data.partner_one_name')
            ->assertJsonPath('data.weddingDate', '2026-12-22');
    }

    public function test_it_returns_json_404_when_no_wedding_exists(): void
    {
        $this->getJson('/api/wedding')
            ->assertNotFound()
            ->assertExactJson([
                'message' => 'Wedding not found.',
            ]);
    }
}
