<?php

namespace Database\Factories;

use App\Models\Wedding;
use App\Models\WeddingFaqEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WeddingFaqEntry> */ class WeddingFaqEntryFactory extends Factory
{
    protected $model = WeddingFaqEntry::class;

    public function definition(): array
    {
        return ['wedding_id' => Wedding::factory(), 'question' => fake()->sentence().'?', 'answer' => fake()->paragraph(), 'sort_order' => 0, 'is_published' => false];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }
}
