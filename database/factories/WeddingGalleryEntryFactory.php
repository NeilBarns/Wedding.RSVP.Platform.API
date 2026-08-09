<?php

namespace Database\Factories;

use App\Models\Wedding;
use App\Models\WeddingGalleryEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WeddingGalleryEntry> */ class WeddingGalleryEntryFactory extends Factory
{
    protected $model = WeddingGalleryEntry::class;

    public function definition(): array
    {
        return ['wedding_id' => Wedding::factory(), 'image_url' => 'https://example.test/photo.jpg', 'alt_text' => fake()->sentence(), 'caption' => fake()->sentence(), 'sort_order' => 0, 'is_published' => false];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }
}
