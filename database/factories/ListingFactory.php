<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Listing;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Listing> */
class ListingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'platform' => fake()->randomElement(['facebook', 'google', 'bing']),
            'external_id' => (string) fake()->unique()->numberBetween(100000000, 999999999),
            'status' => 'synced',
            'name' => fake()->company(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'postal_code' => fake()->postcode(),
            'country' => 'US',
            'phone' => fake()->phoneNumber(),
            'website' => fake()->url(),
            'categories' => [fake()->word(), fake()->word()],
            'business_hours' => [
                'monday' => ['open' => '09:00', 'close' => '17:00'],
                'tuesday' => ['open' => '09:00', 'close' => '17:00'],
            ],
            'description' => fake()->paragraph(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'last_synced_at' => now(),
        ];
    }

    public function facebook(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'facebook',
        ]);
    }

    public function google(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'google',
        ]);
    }

    public function bing(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'bing',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'last_synced_at' => null,
        ]);
    }

    public function withError(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'error',
            'error_message' => 'API connection failed',
        ]);
    }

    public function withDiscrepancies(): static
    {
        return $this->state(fn (array $attributes) => [
            'discrepancies' => [
                'phone' => [
                    'local' => '+1-555-123-4567',
                    'platform' => '+1-555-999-8888',
                ],
            ],
        ]);
    }
}
