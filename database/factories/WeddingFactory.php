<?php

namespace Database\Factories;

use App\Models\Wedding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wedding>
 */
class WeddingFactory extends Factory
{
    protected $model = Wedding::class;

    public function definition(): array
    {
        return [
            'partner_one_name' => fake()->name(),
            'partner_two_name' => fake()->name(),
            'wedding_date' => fake()->dateTimeBetween('+1 month', '+2 years'),
            'rsvp_deadline' => null,
            'dress_code' => null,
            'status' => Wedding::STATUS_DRAFT,
            'theme_key' => null,
            'primary_color' => null,
            'secondary_color' => null,
            'accent_color' => null,
            'background_color' => null,
            'heading_font' => null,
            'body_font' => null,
        ];
    }
}
