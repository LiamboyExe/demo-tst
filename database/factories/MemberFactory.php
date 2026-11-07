<?php

namespace Database\Factories;

use App\Enums\MembershipType;
use App\Models\Branch;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'branch_id' => Branch::factory(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'alias' => fake()->optional(0.7)->firstName(),
            'birthdate' => fake()->dateTimeBetween('-55 years', '-18 years')->format('Y-m-d'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('09#########'),
            'address' => fake()->streetAddress().', '.fake()->city(),
            'membership_type' => fake()->randomElement(MembershipType::values()),
            'status' => fake()->randomElement(['Active', 'Active', 'Inactive', 'Pending']),
            'join_date' => fake()->dateTimeBetween('-3 years')->format('Y-m-d'),
            'profile_slug' => Str::slug($firstName.' '.$lastName).'-'.Str::lower(Str::random(6)),
            'approved' => true,
        ];
    }

    /**
     * Indicate that the member's public profile is not approved.
     */
    public function unapproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'approved' => false,
        ]);
    }
}
