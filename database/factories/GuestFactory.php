<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guest>
 */
class GuestFactory extends Factory
{
    protected $model = Guest::class;

    public function definition(): array
    {
        return [
            'invitation_id' => Invitation::factory(),
            'full_name' => fake()->name(),
            'guest_type' => Guest::TYPE_ADULT,
            'attendance_status' => Guest::ATTENDANCE_PENDING,
            'dietary_requirements' => null,
            'accessibility_requirements' => null,
            'sort_order' => 0,
        ];
    }

    public function child(): static
    {
        return $this->state(fn (array $attributes): array => [
            'guest_type' => Guest::TYPE_CHILD,
        ]);
    }

    public function attending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'attendance_status' => Guest::ATTENDANCE_ATTENDING,
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes): array => [
            'attendance_status' => Guest::ATTENDANCE_DECLINED,
        ]);
    }
}
