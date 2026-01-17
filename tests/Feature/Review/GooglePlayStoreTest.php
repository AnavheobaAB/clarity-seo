<?php

declare(strict_types=1);

namespace Tests\Feature\Review;

use App\Models\Location;
use App\Models\Review;
use App\Models\Tenant;
use App\Services\Review\GooglePlayStoreService;
use Google\Service\AndroidPublisher;
use Google\Service\AndroidPublisher\Resource\Reviews;
use Google\Service\AndroidPublisher\Review as GoogleReview;
use Google\Service\AndroidPublisher\Comment;
use Google\Service\AndroidPublisher\UserComment;
use Google\Service\AndroidPublisher\Timestamp;
use Google\Service\AndroidPublisher\ReviewsListResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GooglePlayStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->location = Location::factory()->create([
            'tenant_id' => $this->tenant->id,
            'google_play_package_name' => 'com.example.app'
        ]);
    }

    public function test_it_can_sync_reviews_using_mock()
    {
        // 1. Setup Mock Data
        $timestamp = new Timestamp();
        $timestamp->setSeconds(time());

        $userComment = new UserComment();
        $userComment->setStarRating(5);
        $userComment->setText('Great app!');
        $userComment->setLastModified($timestamp);

        $comment = new Comment();
        $comment->setUserComment($userComment);

        $googleReview = new GoogleReview();
        $googleReview->setReviewId('review_123');
        $googleReview->setAuthorName('Jane Doe');
        $googleReview->setComments([$comment]);

        $listResponse = new ReviewsListResponse();
        $listResponse->setReviews([$googleReview]);

        // 2. Mock Google Service
        $reviewsResource = Mockery::mock(Reviews::class);
        $reviewsResource->shouldReceive('listReviews')
            ->with('com.example.app')
            ->once()
            ->andReturn($listResponse);

        $publisherService = Mockery::mock(AndroidPublisher::class);
        $publisherService->reviews = $reviewsResource;

        // 3. Execute Service
        $service = new GooglePlayStoreService();
        $service->setPublisherService($publisherService);
        
        $count = $service->syncReviews($this->location);

        // 4. Assertions
        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('reviews', [
            'external_id' => 'review_123',
            'author_name' => 'Jane Doe',
            'rating' => 5,
            'content' => 'Great app!',
            'platform' => 'google_play'
        ]);
    }

    public function test_it_can_reply_to_review_using_mock()
    {
        // 1. Setup Data
        $review = Review::factory()->create([
            'location_id' => $this->location->id,
            'platform' => 'google_play',
            'external_id' => 'review_123'
        ]);

        // 2. Mock Google Service
        $reviewsResource = Mockery::mock(Reviews::class);
        $reviewsResource->shouldReceive('reply')
            ->with('com.example.app', 'review_123', Mockery::type(AndroidPublisher\ReviewsReplyRequest::class))
            ->once()
            ->andReturn(new AndroidPublisher\ReviewsReplyResponse());

        $publisherService = Mockery::mock(AndroidPublisher::class);
        $publisherService->reviews = $reviewsResource;

        // 3. Execute Service
        $service = new GooglePlayStoreService();
        $service->setPublisherService($publisherService);
        
        $success = $service->replyToReview($review, 'Thank you!');

        // 4. Assertions
        $this->assertTrue($success);
        $this->assertDatabaseHas('review_responses', [
            'review_id' => $review->id,
            'content' => 'Thank you!',
            'status' => 'published',
            'platform_synced' => true
        ]);
    }

    public function test_it_can_sync_reviews_with_developer_reply()
    {
        // 1. Setup Mock Data with Developer Reply
        $timestamp = new Timestamp();
        $timestamp->setSeconds(time());

        $userComment = new UserComment();
        $userComment->setStarRating(4);
        $userComment->setText('Good, but could be better.');
        $userComment->setLastModified($timestamp);

        $devComment = new \Google\Service\AndroidPublisher\DeveloperComment();
        $devComment->setText('Thanks! We are working on it.');
        $devComment->setLastModified($timestamp);

        $comment1 = new Comment();
        $comment1->setUserComment($userComment);

        $comment2 = new Comment();
        $comment2->setDeveloperComment($devComment);

        $googleReview = new GoogleReview();
        $googleReview->setReviewId('review_reply_123');
        $googleReview->setAuthorName('Mark Zuckerberg');
        $googleReview->setComments([$comment1, $comment2]);

        $listResponse = new ReviewsListResponse();
        $listResponse->setReviews([$googleReview]);

        $reviewsResource = Mockery::mock(Reviews::class);
        $reviewsResource->shouldReceive('listReviews')->andReturn($listResponse);
        $publisherService = Mockery::mock(AndroidPublisher::class);
        $publisherService->reviews = $reviewsResource;

        $service = new GooglePlayStoreService();
        $service->setPublisherService($publisherService);
        
        $service->syncReviews($this->location);

        // 2. Assertions
        $review = Review::where('external_id', 'review_reply_123')->first();
        $this->assertNotNull($review);
        $this->assertDatabaseHas('review_responses', [
            'review_id' => $review->id,
            'content' => 'Thanks! We are working on it.',
            'status' => 'published'
        ]);
    }

    public function test_it_handles_duplicate_reviews()
    {
        // 1. Create a review manually
        Review::create([
            'location_id' => $this->location->id,
            'platform' => 'google_play',
            'external_id' => 'review_dup_123',
            'author_name' => 'Original Author',
            'rating' => 1,
            'content' => 'Old content',
            'published_at' => now(),
        ]);

        // 2. Mock same review ID but with updated content
        $timestamp = new Timestamp();
        $timestamp->setSeconds(time());
        $userComment = new UserComment();
        $userComment->setStarRating(5);
        $userComment->setText('Updated content');
        $userComment->setLastModified($timestamp);
        $comment = new Comment();
        $comment->setUserComment($userComment);
        $googleReview = new GoogleReview();
        $googleReview->setReviewId('review_dup_123');
        $googleReview->setAuthorName('New Author');
        $googleReview->setComments([$comment]);
        $listResponse = new ReviewsListResponse();
        $listResponse->setReviews([$googleReview]);

        $reviewsResource = Mockery::mock(Reviews::class);
        $reviewsResource->shouldReceive('listReviews')->andReturn($listResponse);
        $publisherService = Mockery::mock(AndroidPublisher::class);
        $publisherService->reviews = $reviewsResource;

        $service = new GooglePlayStoreService();
        $service->setPublisherService($publisherService);
        
        $service->syncReviews($this->location);

        // 3. Assertions
        $this->assertEquals(1, Review::where('external_id', 'review_dup_123')->count());
        $this->assertDatabaseHas('reviews', [
            'external_id' => 'review_dup_123',
            'content' => 'Updated content',
            'author_name' => 'New Author'
        ]);
    }
}
