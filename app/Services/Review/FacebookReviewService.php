<?php

declare(strict_types=1);

namespace App\Services\Review;

use App\Models\Location;
use App\Models\PlatformCredential;
use App\Models\Review;
use App\Models\ReviewResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookReviewService
{
    protected string $baseUrl;

    protected string $graphVersion;

    public function __construct()
    {
        $this->baseUrl = config('facebook.base_url');
        $this->graphVersion = config('facebook.graph_version');
    }

    /**
     * Get the full API URL for a given endpoint.
     */
    protected function apiUrl(string $endpoint): string
    {
        return "{$this->baseUrl}/{$this->graphVersion}/{$endpoint}";
    }

    /**
     * Sync Facebook reviews for a location.
     *
     * @return int Number of reviews synced
     */
    public function syncFacebookReviews(Location $location, PlatformCredential $credential): int
    {
        $pageId = $credential->getPageId();
        $pageAccessToken = $credential->metadata['page_access_token'] ?? $credential->access_token;

        if (!$pageId) {
            Log::error('No Facebook page ID configured', ['tenant_id' => $credential->tenant_id]);

            return 0;
        }

        // Get page ratings/reviews
        $ratings = $this->getPageRatings($pageId, $pageAccessToken);

        if (!$ratings) {
            Log::warning('No Facebook ratings found', [
                'page_id' => $pageId,
                'location_id' => $location->id,
            ]);

            return 0;
        }

        $syncedCount = 0;

        foreach ($ratings as $rating) {
            if (!isset($rating['rating'])) {
                continue;
            }

            // Facebook ratings can have reviews
            $content = $rating['review_text'] ?? $rating['recommendation_type'] ?? null;
            $authorName = $rating['reviewer']['name'] ?? 'Anonymous';
            $createdTime = $rating['created_time'] ?? now()->toIso8601String();

            Review::updateOrCreate(
                [
                    'location_id' => $location->id,
                    'platform' => 'facebook',
                    'external_id' => $rating['open_graph_story']['id'] ?? md5($authorName . $createdTime),
                ],
                [
                    'author_name' => $authorName,
                    'author_image' => $rating['reviewer']['picture']['data']['url'] ?? null,
                    'rating' => $rating['rating'],
                    'content' => $content,
                    'published_at' => \Carbon\Carbon::parse($createdTime),
                    'metadata' => $rating,
                ]
            );

            $syncedCount++;
        }

        Log::info('Facebook reviews synced', [
            'location_id' => $location->id,
            'page_id' => $pageId,
            'synced_count' => $syncedCount,
        ]);

        return $syncedCount;
    }

    /**
     * Publish a review response to Facebook.
     */
    public function publishResponse(ReviewResponse $response, PlatformCredential $credential): bool
    {
        $review = $response->review;

        if ($review->platform !== 'facebook') {
            Log::error('Cannot publish to non-Facebook review', [
                'review_id' => $review->id,
                'platform' => $review->platform,
            ]);

            return false;
        }

        $pageId = $credential->getPageId();
        $pageAccessToken = $credential->metadata['page_access_token'] ?? $credential->access_token;

        if (!$pageId) {
            Log::error('No Facebook page ID configured', ['tenant_id' => $credential->tenant_id]);

            return false;
        }

        // Get the open_graph_story ID from review metadata
        // According to Facebook docs: https://stackoverflow.com/a/41683980
        // To reply to a review, POST to /{open_graph_story_id}/comments
        $openGraphStoryId = $review->metadata['open_graph_story']['id'] ?? null;

        if (!$openGraphStoryId) {
            Log::error('No open_graph_story ID found in review metadata', [
                'review_id' => $review->id,
                'external_id' => $review->external_id,
                'metadata_keys' => array_keys($review->metadata ?? []),
            ]);

            return false;
        }

        try {
            // Facebook API: POST /{open_graph_story_id}/comments
            // https://developers.facebook.com/docs/graph-api/reference/v24.0/object/comments
            $url = $this->apiUrl("{$openGraphStoryId}/comments");

            $httpResponse = Http::post($url, [
                'access_token' => $pageAccessToken,
                'message' => $response->content,
            ]);

            if (!$httpResponse->successful()) {
                Log::error('Facebook API error: Failed to publish review response', [
                    'page_id' => $pageId,
                    'open_graph_story_id' => $openGraphStoryId,
                    'review_id' => $review->id,
                    'status' => $httpResponse->status(),
                    'error' => $httpResponse->json('error'),
                    'url' => $url,
                ]);

                return false;
            }

            // Mark response as published
            $response->publish();

            Log::info('Facebook review response published', [
                'review_id' => $review->id,
                'response_id' => $response->id,
                'page_id' => $pageId,
                'open_graph_story_id' => $openGraphStoryId,
            ]);

            return true;
        } catch (ConnectionException $e) {
            Log::error('Facebook connection error', [
                'error' => $e->getMessage(),
                'review_id' => $review->id,
            ]);

            return false;
        }
    }

    /**
     * Get Facebook page ratings (reviews).
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function getPageRatings(string $pageId, string $accessToken): ?array
    {
        try {
            $response = Http::get($this->apiUrl("{$pageId}/ratings"), [
                'access_token' => $accessToken,
                'fields' => implode(',', [
                    'rating',
                    'review_text',
                    'recommendation_type',
                    'created_time',
                    'open_graph_story',
                    'reviewer{name,picture}',
                ]),
                'limit' => 100, // Fetch up to 100 reviews
            ]);

            if (!$response->successful()) {
                Log::error('Facebook API error: Failed to get ratings', [
                    'page_id' => $pageId,
                    'status' => $response->status(),
                    'error' => $response->json('error'),
                ]);

                return null;
            }

            return $response->json('data', []);
        } catch (ConnectionException $e) {
            Log::error('Facebook connection error', [
                'error' => $e->getMessage(),
                'page_id' => $pageId,
            ]);

            return null;
        }
    }

    /**
     * Check if tenant has Facebook review access.
     */
    public function hasReviewAccess(PlatformCredential $credential): bool
    {
        $scopes = $credential->scopes ?? [];

        return in_array('pages_read_engagement', $scopes) ||
            in_array('pages_manage_engagement', $scopes);
    }
}
