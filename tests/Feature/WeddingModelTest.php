<?php

namespace Tests\Feature;

use App\Enums\WeddingTemplateKey;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeddingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_wedding_can_be_persisted_with_defaults_and_nullable_theme_fields(): void
    {
        $wedding = Wedding::query()->create([
            'partner_one_name' => 'Alex Rivera',
            'partner_two_name' => 'Jamie Santos',
            'wedding_date' => '2027-04-10',
        ]);

        $wedding->refresh();

        $this->assertSame(Wedding::STATUS_DRAFT, $wedding->status);
        $this->assertSame(WeddingTemplateKey::EditorialLinenV1->value, $wedding->template_key);
        $this->assertSame('2027-04-10', $wedding->wedding_date->format('Y-m-d'));
        $this->assertNull($wedding->rsvp_deadline);
        $this->assertNull($wedding->theme_key);
        $this->assertNull($wedding->primary_color);
        $this->assertNull($wedding->secondary_color);
        $this->assertNull($wedding->accent_color);
        $this->assertNull($wedding->background_color);
        $this->assertNull($wedding->heading_font);
        $this->assertNull($wedding->body_font);
    }

    public function test_date_fields_are_cast_to_dates(): void
    {
        $wedding = Wedding::factory()->create([
            'wedding_date' => '2027-05-15',
            'rsvp_deadline' => '2027-04-15',
        ]);

        $this->assertInstanceOf(\DateTimeInterface::class, $wedding->wedding_date);
        $this->assertInstanceOf(\DateTimeInterface::class, $wedding->rsvp_deadline);
        $this->assertSame('2027-05-15', $wedding->wedding_date->format('Y-m-d'));
        $this->assertSame('2027-04-15', $wedding->rsvp_deadline->format('Y-m-d'));
    }
}
