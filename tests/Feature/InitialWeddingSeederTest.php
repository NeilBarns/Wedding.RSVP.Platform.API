<?php

namespace Tests\Feature;

use App\Enums\WeddingTemplateKey;
use App\Models\Wedding;
use Database\Seeders\InitialWeddingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialWeddingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_initial_wedding_and_is_idempotent(): void
    {
        $this->seed(InitialWeddingSeeder::class);
        $this->seed(InitialWeddingSeeder::class);

        $this->assertDatabaseCount('weddings', 1);
        $this->assertDatabaseHas('weddings', [
            'partner_one_name' => 'Neil Barnedo',
            'partner_two_name' => 'Hazel Elago',
            'wedding_date' => '2026-12-22',
            'rsvp_deadline' => null,
            'dress_code' => 'Filipiniana',
            'status' => Wedding::STATUS_DRAFT,
            'template_key' => WeddingTemplateKey::EditorialLinenV1->value,
            'theme_key' => null,
            'primary_color' => null,
            'secondary_color' => null,
            'accent_color' => null,
            'background_color' => null,
            'heading_font' => null,
            'body_font' => null,
        ]);
    }
}
