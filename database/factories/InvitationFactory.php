<?php

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        $formats = [
            fn (): string => 'The '.fake()->lastName().' Family',
            fn (): string => fake()->name().' & '.fake()->name(),
            fn (): string => fake()->name(),
        ];

        return [
            'wedding_id' => Wedding::factory(),
            'display_name' => fake()->randomElement($formats)(),
            'contact_person_name' => fake()->optional()->name(),
            'contact_number' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'token_hash' => hash('sha256', Str::random(64)),
            'status' => Invitation::STATUS_DRAFT,
            'internal_notes' => null,
            'first_opened_at' => null,
            'last_opened_at' => null,
            'submitted_at' => null,
            'locked_at' => null,
            'response_contact_number' => null,
            'response_email' => null,
            'message_to_couple' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Invitation::STATUS_READY,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Invitation::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Invitation::STATUS_LOCKED,
            'locked_at' => now(),
        ]);
    }
}
