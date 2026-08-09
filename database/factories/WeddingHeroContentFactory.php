<?php

namespace Database\Factories;

use App\Models\Wedding;
use App\Models\WeddingHeroContent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WeddingHeroContent> */ class WeddingHeroContentFactory extends Factory
{
    protected $model = WeddingHeroContent::class;

    public function definition(): array
    {
        return ['wedding_id' => Wedding::factory(), 'eyebrow' => fake()->sentence(3), 'headline' => fake()->sentence(5), 'subheadline' => fake()->sentence(), 'hero_media_url' => 'https://example.test/hero.jpg', 'hero_media_alt_text' => fake()->sentence(), 'is_published' => false];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }
}
