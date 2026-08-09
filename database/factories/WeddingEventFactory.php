<?php

namespace Database\Factories;

use App\Models\Wedding;
use App\Models\WeddingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WeddingEvent> */ class WeddingEventFactory extends Factory
{
    protected $model = WeddingEvent::class;

    public function definition(): array
    {
        return ['wedding_id' => Wedding::factory(), 'title' => fake()->words(2, true), 'event_type' => 'ceremony', 'event_date' => fake()->date(), 'start_time' => '15:00', 'end_time' => '16:00', 'venue_name' => fake()->company(), 'address_line' => fake()->address(), 'map_url' => null, 'description' => fake()->sentence(), 'dress_code_override' => null, 'sort_order' => 0, 'is_published' => false];
    }

    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }
}
