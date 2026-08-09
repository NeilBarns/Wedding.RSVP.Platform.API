<?php

namespace Database\Factories;

use App\Models\Wedding;
use App\Models\WeddingStoryEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WeddingStoryEntry> */ class WeddingStoryEntryFactory extends Factory
{
    protected $model = WeddingStoryEntry::class;

    public function definition(): array
    {
        return ['wedding_id' => Wedding::factory(), 'title' => fake()->sentence(3), 'body' => fake()->paragraph(), 'image_url' => null, 'image_alt_text' => null, 'event_date' => null, 'sort_order' => 0, 'is_published' => false];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }
}
