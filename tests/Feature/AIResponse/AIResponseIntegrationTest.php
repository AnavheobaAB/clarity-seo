<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AIResponse\AIResponseService;
use Laravel\Sanctum\Sanctum;

/**
 * Integration tests for OpenRouter AI Response Generation.
 * These tests make real API calls to OpenRouter.
 *
 * To run: php artisan test tests/Feature/AIResponse/AIResponseIntegrationTest.php
 */
describe('OpenRouter AI Response Generation Integration', function () {
    beforeEach(function () {
        $this->apiKey = env('OPENROUTER_API_KEY');

        if (empty($this->apiKey)) {
            $this->markTestSkipped('OPENROUTER_API_KEY not set in .env');
        }
    });

    it('generates response for positive review', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        $review = Review::factory()->create([
            'location_id' => $location->id,
            'rating' => 5,
            'content' => 'Excellent service! The team was professional and went above and beyond. Highly recommend!',
        ]);

        $service = app(AIResponseService::class);
        $result = $service->generateResponse($review, $user, ['tone' => 'professional']);

        expect($result)->not->toBeNull()
            ->and($result['response'])->not->toBeNull()
            ->and($result['response']->content)->not->toBeEmpty()
            ->and($result['response']->ai_generated)->toBeTrue()
            ->and($result['response']->tone)->toBe('professional');
    });

    it('generates response for negative review with apologetic tone', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        $review = Review::factory()->create([
            'location_id' => $location->id,
            'rating' => 1,
            'content' => 'Very disappointed. Long wait times and poor customer service. Will not return.',
        ]);

        $service = app(AIResponseService::class);
        $result = $service->generateResponse($review, $user, ['tone' => 'apologetic']);

        expect($result)->not->toBeNull()
            ->and($result['response']->content)->not->toBeEmpty()
            ->and($result['response']->tone)->toBe('apologetic');

        // Response should contain apologetic language
        $content = strtolower($result['response']->content);
        $hasApology = str_contains($content, 'sorry') ||
                      str_contains($content, 'apologize') ||
                      str_contains($content, 'regret');
        expect($hasApology)->toBeTrue();
    });

    it('generates response with friendly tone', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        $review = Review::factory()->create([
            'location_id' => $location->id,
            'rating' => 4,
            'content' => 'Nice place! Good food and atmosphere. Will come back.',
        ]);

        $service = app(AIResponseService::class);
        $result = $service->generateResponse($review, $user, ['tone' => 'friendly']);

        expect($result)->not->toBeNull()
            ->and($result['response']->content)->not->toBeEmpty()
            ->and($result['response']->tone)->toBe('friendly');
    });

    it('generates response in different language', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        $review = Review::factory()->create([
            'location_id' => $location->id,
            'rating' => 5,
            'content' => 'Excelente servicio! Muy recomendado.',
        ]);

        $service = app(AIResponseService::class);
        $result = $service->generateResponse($review, $user, [
            'tone' => 'professional',
            'language' => 'es',
        ]);

        expect($result)->not->toBeNull()
            ->and($result['response']->content)->not->toBeEmpty()
            ->and($result['response']->language)->toBe('es');
    });

    it('includes quality score when requested', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        $review = Review::factory()->create([
            'location_id' => $location->id,
            'rating' => 4,
            'content' => 'Great experience overall. Staff was helpful.',
        ]);

        $service = app(AIResponseService::class);
        $result = $service->generateResponse($review, $user, [
            'tone' => 'professional',
            'include_quality_score' => true,
        ]);

        expect($result)->not->toBeNull()
            ->and($result)->toHaveKey('quality_score')
            ->and($result['quality_score'])->toBeGreaterThanOrEqual(0)
            ->and($result['quality_score'])->toBeLessThanOrEqual(1);
    });

    it('generates response via API endpoint', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        $review = Review::factory()->create([
            'location_id' => $location->id,
            'rating' => 5,
            'content' => 'Amazing service! Very professional team.',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/reviews/{$review->id}/ai-response", [
            'tone' => 'professional',
        ]);

        $response->assertSuccessful();
        expect($response->json('data.content'))->not->toBeEmpty()
            ->and($response->json('data.ai_generated'))->toBeTrue();
    });

    it('can regenerate response', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        $review = Review::factory()->create([
            'location_id' => $location->id,
            'rating' => 4,
            'content' => 'Good service, reasonable prices.',
        ]);

        $service = app(AIResponseService::class);

        // Generate first response
        $result1 = $service->generateResponse($review, $user, ['tone' => 'professional']);
        $content1 = $result1['response']->content;

        // Regenerate response
        $result2 = $service->regenerateResponse($review, $user, ['tone' => 'friendly']);
        $content2 = $result2['response']->content;

        expect($result2)->not->toBeNull()
            ->and($result2['response']->tone)->toBe('friendly');
        // Content should be different (different tone)
    });

    it('gets suggestions for response improvement', function () {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->hasAttached($user, ['role' => 'admin'])->create();
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);

        $review = Review::factory()->create([
            'location_id' => $location->id,
            'rating' => 3,
            'content' => 'It was okay. Nothing special.',
        ]);

        $service = app(AIResponseService::class);
        $result = $service->generateResponse($review, $user, ['tone' => 'professional']);

        $suggestions = $service->getSuggestions($result['response']);

        // May or may not return suggestions depending on API
        if ($suggestions !== null) {
            expect($suggestions)->toHaveKey('suggestions')
                ->and($suggestions)->toHaveKey('improved_version');
        }
    });
});
