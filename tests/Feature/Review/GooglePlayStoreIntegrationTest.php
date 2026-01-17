<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\PlatformCredential;
use App\Models\Review;
use App\Models\Tenant;
use App\Services\Review\GooglePlayStoreService;
use Illuminate\Support\Facades\Config;

uses()->group('integration', 'google_play');

/**
 * Integration tests for Google Play Store Reviews.
 * These tests make real API calls to Google Play Developer API.
 */
describe('Google Play Store Integration', function () {
    beforeEach(function () {
        $this->packageName = env('GOOGLE_PLAY_PACKAGE_NAME');
        $this->serviceAccountJson = env('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON');

        if (empty($this->packageName) || empty($this->serviceAccountJson)) {
            $this->markTestSkipped('Google Play Store credentials not set in .env');
        }
    });

    it('can sync reviews from Google Play Store', function () {
        $tenant = Tenant::factory()->create();
        $location = Location::factory()->create([
            'tenant_id' => $tenant->id,
            'google_play_package_name' => $this->packageName,
        ]);

        // Store credentials
        PlatformCredential::factory()->create([
            'tenant_id' => $tenant->id,
            'platform' => 'google_play',
            'external_id' => $this->packageName,
            'access_token' => $this->serviceAccountJson,
            'is_active' => true,
        ]);

        $service = app(GooglePlayStoreService::class);
        
        // Execute sync
        $result = $service->syncReviews($location);

        // We assert that the call succeeded (didn't throw exception)
        // Even if 0 reviews are found (e.g. none in the last week)
        expect($result)->toBeGreaterThanOrEqual(0);

        // Verify reviews stored in database match the result count
        $reviews = $location->reviews()->where('platform', 'google_play')->get();
        expect($reviews->count())->toBe($result);
        
        if ($result === 0) {
            echo "\n[Notice] API returned 0 reviews for {$this->packageName}. This is normal if no reviews were posted recently.\n";
        } else {
            $review = $reviews->first();
            expect($review->external_id)->not->toBeEmpty()
                ->and($review->author_name)->not->toBeEmpty()
                ->and($review->rating)->toBeGreaterThanOrEqual(1)
                ->and($review->rating)->toBeLessThanOrEqual(5);
        }
    });

    it('can reply to a review', function () {
        $testReviewId = env('GOOGLE_PLAY_TEST_REVIEW_ID');
        
        if (empty($testReviewId)) {
            $this->markTestSkipped('GOOGLE_PLAY_TEST_REVIEW_ID not set in .env');
        }

        $tenant = Tenant::factory()->create();
        $location = Location::factory()->create([
            'tenant_id' => $tenant->id,
            'google_play_package_name' => $this->packageName,
        ]);

        PlatformCredential::factory()->create([
            'tenant_id' => $tenant->id,
            'platform' => 'google_play',
            'external_id' => $this->packageName,
            'access_token' => $this->serviceAccountJson,
            'is_active' => true,
        ]);

        // Create a local review that matches the external one
        $review = Review::factory()->create([
            'location_id' => $location->id,
            'platform' => 'google_play',
            'external_id' => $testReviewId,
            'rating' => 5,
            'author_name' => 'Test User',
        ]);

        $service = app(GooglePlayStoreService::class);
        
        $responseContent = 'Thank you for your feedback! This is a test response via API Integration.';
        
        // Execute reply
        $success = $service->replyToReview($review, $responseContent);

        expect($success)->toBeTrue();
        
        // Verify response stored/updated in DB
        expect($review->response()->exists())->toBeTrue();
        $response = $review->response;
        expect($response->content)->toBe($responseContent)
            ->and($response->status)->toBe('published')
            ->and($response->platform_synced)->toBeTrue();
    });
});
