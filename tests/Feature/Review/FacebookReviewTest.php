<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Models\Location;
use App\Models\PlatformCredential;
use App\Models\Review;
use App\Models\ReviewResponse;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Review\FacebookReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Tenant $tenant;

    protected Location $location;

    protected PlatformCredential $credential;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Create tenant
        $this->tenant = Tenant::factory()->create();
        $this->tenant->users()->attach($this->user, ['role' => 'owner']);
        $this->user->update(['current_tenant_id' => $this->tenant->id]);

        // Create location
        $this->location = Location::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create Facebook credentials
        $this->credential = PlatformCredential::create([
            'tenant_id' => $this->tenant->id,
            'platform' => PlatformCredential::PLATFORM_FACEBOOK,
            'access_token' => 'test_access_token',
            'scopes' => ['pages_read_engagement', 'pages_manage_engagement'],
            'metadata' => [
                'page_id' => 'test_page_id',
                'page_access_token' => 'test_page_access_token',
            ],
            'is_active' => true,
        ]);
    }

    public function test_it_can_sync_facebook_reviews()
    {
        // Mock Facebook API response
        Http::fake([
            '*ratings*' => Http::response([
                'data' => [
                    [
                        'rating' => 5,
                        'review_text' => 'Great service!',
                        'created_time' => '2025-01-10T10:00:00+0000',
                        'open_graph_story' => ['id' => 'story_123'],
                        'reviewer' => [
                            'name' => 'John Doe',
                            'picture' => ['data' => ['url' => 'https://example.com/pic.jpg']],
                        ],
                    ],
                    [
                        'rating' => 4,
                        'review_text' => 'Good experience',
                        'created_time' => '2025-01-11T12:00:00+0000',
                        'open_graph_story' => ['id' => 'story_456'],
                        'reviewer' => [
                            'name' => 'Jane Smith',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new FacebookReviewService;
        $count = $service->syncFacebookReviews($this->location, $this->credential);

        $this->assertEquals(2, $count);

        // Verify reviews were created
        $reviews = Review::where('location_id', $this->location->id)
            ->where('platform', 'facebook')
            ->get();

        $this->assertCount(2, $reviews);

        $firstReview = $reviews->where('external_id', 'story_123')->first();
        $this->assertEquals('John Doe', $firstReview->author_name);
        $this->assertEquals(5, $firstReview->rating);
        $this->assertEquals('Great service!', $firstReview->content);
    }

    public function test_it_handles_duplicate_reviews()
    {
        // Create existing review
        Review::create([
            'location_id' => $this->location->id,
            'platform' => 'facebook',
            'external_id' => 'story_123',
            'author_name' => 'John Doe',
            'rating' => 5,
            'content' => 'Great service!',
            'published_at' => now(),
        ]);

        // Mock same review
        Http::fake([
            '*ratings*' => Http::response([
                'data' => [
                    [
                        'rating' => 5,
                        'review_text' => 'Great service!',
                        'created_time' => '2025-01-10T10:00:00+0000',
                        'open_graph_story' => ['id' => 'story_123'],
                        'reviewer' => ['name' => 'John Doe'],
                    ],
                ],
            ], 200),
        ]);

        $service = new FacebookReviewService;
        $count = $service->syncFacebookReviews($this->location, $this->credential);

        // Should still report 1 synced (updateOrCreate)
        $this->assertEquals(1, $count);

        // Should not create duplicate
        $this->assertEquals(1, Review::where('location_id', $this->location->id)->count());
    }

    public function test_it_can_publish_response_to_facebook()
    {
        // Create review
        $review = Review::create([
            'location_id' => $this->location->id,
            'platform' => 'facebook',
            'external_id' => 'story_123',
            'author_name' => 'John Doe',
            'rating' => 5,
            'content' => 'Great service!',
            'published_at' => now(),
            'metadata' => [
                'open_graph_story' => ['id' => 'rating_id_123'],
            ],
        ]);

        // Create response
        $response = ReviewResponse::create([
            'review_id' => $review->id,
            'user_id' => $this->user->id,
            'content' => 'Thank you for your feedback!',
            'status' => 'approved',
        ]);

        // Mock Facebook API
        Http::fake([
            '*ratings/rating_id_123*' => Http::response(['success' => true], 200),
        ]);

        $service = new FacebookReviewService;
        $success = $service->publishResponse($response, $this->credential);

        $this->assertTrue($success);

        // Verify response was marked as published
        $this->assertTrue($response->fresh()->isPublished());
    }

    public function test_it_handles_api_errors_gracefully()
    {
        // Mock API error
        Http::fake([
            '*ratings*' => Http::response([
                'error' => [
                    'message' => 'Invalid access token',
                    'type' => 'OAuthException',
                ],
            ], 400),
        ]);

        $service = new FacebookReviewService;
        $count = $service->syncFacebookReviews($this->location, $this->credential);

        $this->assertEquals(0, $count);
    }

    public function test_it_requires_page_id_to_sync()
    {
        // Remove page_id from metadata
        $this->credential->update(['metadata' => []]);

        $service = new FacebookReviewService;
        $count = $service->syncFacebookReviews($this->location, $this->credential);

        $this->assertEquals(0, $count);
    }

    public function test_it_can_check_review_access_permissions()
    {
        $service = new FacebookReviewService;

        // Has required permissions
        $this->assertTrue($service->hasReviewAccess($this->credential));

        // Update to missing permissions
        $this->credential->update(['scopes' => ['pages_show_list']]);
        $this->assertFalse($service->hasReviewAccess($this->credential));
    }
}
