<?php

use App\Models\Location;
use App\Models\PlatformCredential;
use App\Models\Review;
use App\Models\ReviewResponse;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Review\FacebookReviewService;
use App\Services\Review\ReviewService;
use Illuminate\Support\Facades\Http;

uses()->group('integration', 'facebook', 'publish');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->tenant = Tenant::factory()->create();
    $this->user->tenants()->attach($this->tenant, ['role' => 'owner']);
    $this->user->update(['current_tenant_id' => $this->tenant->id]);

    $this->reviewService = app(ReviewService::class);
});

test('it publishes response to correct Facebook page when location has facebook_page_id', function () {
    // Create location with facebook_page_id
    $location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'facebook_page_id' => 'page_123',
    ]);

    // Create credential for page_123
    $credential = PlatformCredential::create([
        'tenant_id' => $this->tenant->id,
        'platform' => 'facebook',
        'external_id' => 'page_123',
        'access_token' => 'token_123',
        'metadata' => [
            'page_id' => 'page_123',
            'page_access_token' => 'page_token_123',
        ],
        'is_active' => true,
    ]);

    // Create review
    $review = Review::create([
        'location_id' => $location->id,
        'platform' => 'facebook',
        'external_id' => 'review_1',
        'author_name' => 'John Doe',
        'rating' => 5,
        'content' => 'Great service!',
        'published_at' => now(),
        'metadata' => [
            'open_graph_story' => ['id' => 'rating_123'],
        ],
    ]);

    // Create response
    $response = ReviewResponse::create([
        'review_id' => $review->id,
        'user_id' => $this->user->id,
        'content' => 'Thank you!',
        'status' => 'approved',
    ]);

    // Mock Facebook API
    Http::fake([
        '*/page_123/ratings/rating_123*' => Http::response(['success' => true], 200),
    ]);

    // Publish response
    $result = $this->reviewService->publishResponse($response);

    expect($result->isPublished())->toBeTrue();

    // Verify correct API was called
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'page_123/ratings/rating_123');
    });
});

test('it uses correct credential when multiple Facebook pages exist', function () {
    // Create TWO locations with different pages
    $location1 = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'facebook_page_id' => 'page_A',
    ]);

    $location2 = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'facebook_page_id' => 'page_B',
    ]);

    // Create credentials for BOTH pages
    PlatformCredential::create([
        'tenant_id' => $this->tenant->id,
        'platform' => 'facebook',
        'external_id' => 'page_A',
        'access_token' => 'token_A',
        'metadata' => ['page_id' => 'page_A', 'page_access_token' => 'page_token_A'],
        'is_active' => true,
    ]);

    PlatformCredential::create([
        'tenant_id' => $this->tenant->id,
        'platform' => 'facebook',
        'external_id' => 'page_B',
        'access_token' => 'token_B',
        'metadata' => ['page_id' => 'page_B', 'page_access_token' => 'page_token_B'],
        'is_active' => true,
    ]);

    // Create review for location1 (page_A)
    $review = Review::create([
        'location_id' => $location1->id,
        'platform' => 'facebook',
        'external_id' => 'review_A',
        'author_name' => 'Customer A',
        'rating' => 5,
        'content' => 'Love it!',
        'published_at' => now(),
        'metadata' => ['open_graph_story' => ['id' => 'rating_A']],
    ]);

    $response = ReviewResponse::create([
        'review_id' => $review->id,
        'user_id' => $this->user->id,
        'content' => 'Thanks!',
        'status' => 'approved',
    ]);

    Http::fake([
        '*/page_A/ratings/*' => Http::response(['success' => true], 200),
        '*/page_B/ratings/*' => Http::response(['success' => true], 200),
    ]);

    // Publish
    $this->reviewService->publishResponse($response);

    // Should use page_A credential, NOT page_B
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'page_A/ratings/rating_A');
    });

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'page_B');
    });
});

test('it falls back to any credential if location has no facebook_page_id', function () {
    // Location WITHOUT facebook_page_id
    $location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'facebook_page_id' => null,
    ]);

    // Create credential (without external_id for legacy support)
    PlatformCredential::create([
        'tenant_id' => $this->tenant->id,
        'platform' => 'facebook',
        'external_id' => null,
        'access_token' => 'token_fallback',
        'metadata' => [
            'page_id' => 'page_fallback',
            'page_access_token' => 'page_token_fallback',
        ],
        'is_active' => true,
    ]);

    $review = Review::create([
        'location_id' => $location->id,
        'platform' => 'facebook',
        'external_id' => 'review_fb',
        'author_name' => 'User',
        'rating' => 4,
        'content' => 'Good',
        'published_at' => now(),
        'metadata' => ['open_graph_story' => ['id' => 'rating_fb']],
    ]);

    $response = ReviewResponse::create([
        'review_id' => $review->id,
        'user_id' => $this->user->id,
        'content' => 'Thank you!',
        'status' => 'approved',
    ]);

    Http::fake([
        '*' => Http::response(['success' => true], 200),
    ]);

    // Should not throw error
    $result = $this->reviewService->publishResponse($response);

    expect($result->isPublished())->toBeTrue();

    // Verify HTTP request was sent
    Http::assertSent(function ($request) {
        return true; // Any request was sent
    });
});

test('it throws exception if no valid Facebook credential exists', function () {
    $location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'facebook_page_id' => 'page_xyz',
    ]);

    // NO credential for this page

    $review = Review::create([
        'location_id' => $location->id,
        'platform' => 'facebook',
        'external_id' => 'review_xyz',
        'author_name' => 'User',
        'rating' => 5,
        'published_at' => now(),
        'metadata' => ['open_graph_story' => ['id' => 'rating_xyz']],
    ]);

    $response = ReviewResponse::create([
        'review_id' => $review->id,
        'user_id' => $this->user->id,
        'content' => 'Thanks!',
        'status' => 'approved',
    ]);

    // Should throw exception
    expect(fn() => $this->reviewService->publishResponse($response))
        ->toThrow(\Exception::class, 'No valid Facebook credentials found');
});
