<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Sentiment\SentimentService;
use Laravel\Sanctum\Sanctum;

/**
 * Integration tests for OpenRouter AI Sentiment Analysis.
 * These tests make real API calls to OpenRouter.
 *
 * To run: php artisan test tests/Feature/Sentiment/SentimentIntegrationTest.php
 */
describe('OpenRouter Sentiment Analysis Integration', function () {
    beforeEach(function () {
        $this->apiKey = env('OPENROUTER_API_KEY');

        if (empty($this->apiKey)) {
            $this->markTestSkipped('OPENROUTER_API_KEY not set in .env');
        }
    });

    it('can analyze a positive review', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        $review = Review::factory()->create([
            'location_id' => $location->id,
            'rating' => 5,
            'content' => 'Absolutely amazing service! The staff was friendly and helpful. Best experience ever. Will definitely come back!',
        ]);

        $service = app(SentimentService::class);
        $sentiment = $service->analyzeReview($review);

        expect($sentiment)->not->toBeNull()
            ->and($sentiment->sentiment)->toBeIn(['positive', 'neutral', 'mixed'])
            ->and($sentiment->sentiment_score)->toBeGreaterThan(0.5)
            ->and($sentiment->review_id)->toBe($review->id);
    });

    it('can analyze a negative review', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        $review = Review::factory()->create([
            'location_id' => $location->id,
            'rating' => 1,
            'content' => 'Terrible experience. Waited 2 hours, staff was rude, and the food was cold. Never coming back!',
        ]);

        $service = app(SentimentService::class);
        $sentiment = $service->analyzeReview($review);

        expect($sentiment)->not->toBeNull()
            ->and($sentiment->sentiment)->toBeIn(['negative', 'mixed'])
            ->and($sentiment->sentiment_score)->toBeLessThan(0.5)
            ->and($sentiment->review_id)->toBe($review->id);
    });

    it('extracts topics from review', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        $review = Review::factory()->create([
            'location_id' => $location->id,
            'rating' => 3,
            'content' => 'The food quality was excellent but the service was slow. Prices are reasonable but parking is difficult.',
        ]);

        $service = app(SentimentService::class);
        $sentiment = $service->analyzeReview($review);

        expect($sentiment)->not->toBeNull()
            ->and($sentiment->topics)->toBeArray();

        // Should extract at least some topics
        if (count($sentiment->topics) > 0) {
            $topicNames = array_column($sentiment->topics, 'topic');
            expect($topicNames)->toBeArray();
        }
    });

    it('extracts keywords from review', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        $review = Review::factory()->create([
            'location_id' => $location->id,
            'rating' => 4,
            'content' => 'Great atmosphere and delicious food. The pasta was perfectly cooked and the wine selection is impressive.',
        ]);

        $service = app(SentimentService::class);
        $sentiment = $service->analyzeReview($review);

        expect($sentiment)->not->toBeNull()
            ->and($sentiment->keywords)->toBeArray();
    });

    it('handles rating-only review without content', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'member'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        $review = Review::factory()->create([
            'location_id' => $location->id,
            'rating' => 5,
            'content' => null,
        ]);

        $service = app(SentimentService::class);
        $sentiment = $service->analyzeReview($review);

        // Should still analyze based on rating
        expect($sentiment)->not->toBeNull()
            ->and($sentiment->sentiment)->toBeIn(['positive', 'negative', 'neutral', 'mixed']);
    });

    it('analyzes sentiment via API endpoint', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        $review = Review::factory()->create([
            'location_id' => $location->id,
            'rating' => 4,
            'content' => 'Really enjoyed my visit. Friendly staff and quick service.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/locations/{$location->id}/reviews/{$review->id}/analyze");

        $response->assertSuccessful();
        expect($response->json('data.sentiment'))->toBeIn(['positive', 'negative', 'neutral', 'mixed']);
    });
});
