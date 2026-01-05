<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlatformCredential;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlatformCredential> */
class PlatformCredentialFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'platform' => 'facebook',
            'access_token' => fake()->sha256(),
            'refresh_token' => null,
            'token_type' => 'Bearer',
            'expires_at' => now()->addDays(60),
            'scopes' => ['pages_show_list', 'pages_read_engagement', 'pages_manage_metadata'],
            'metadata' => [
                'page_id' => (string) fake()->unique()->numberBetween(100000000, 999999999),
            ],
            'is_active' => true,
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

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withRealFacebookToken(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'facebook',
            'access_token' => env('FACEBOOK_TEST_ACCESS_TOKEN'),
            'metadata' => [
                'page_id' => env('FACEBOOK_TEST_PAGE_ID', '702344112952665'),
            ],
        ]);
    }
}
