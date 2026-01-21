<?php

/**
 * PHP Version of Facebook Live Integration Test
 * Tests real Facebook API with credentials from .env
 * 
 * Usage: php scripts/test-facebook-live.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Illuminate\Support\Facades\Http;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Colors for terminal output
const RED = "\033[0;31m";
const GREEN = "\033[0;32m";
const YELLOW = "\033[1;33m";
const BLUE = "\033[0;34m";
const NC = "\033[0m";

function log_info($message)
{
    echo BLUE . $message . NC . PHP_EOL;
}

function log_success($message)
{
    echo GREEN . "✓ " . $message . NC . PHP_EOL;
}

function log_error($message)
{
    echo RED . "✗ " . $message . NC . PHP_EOL;
}

function log_warning($message)
{
    echo YELLOW . "⚠ " . $message . NC . PHP_EOL;
}

// Header
echo PHP_EOL;
log_info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
log_info("  Facebook Live Integration Test (PHP)");
log_info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
echo PHP_EOL;

// Check credentials
$appId = $_ENV['FACEBOOK_APP_ID'] ?? null;
$appSecret = $_ENV['FACEBOOK_APP_SECRET'] ?? null;
$graphVersion = $_ENV['FACEBOOK_GRAPH_VERSION'] ?? 'v24.0';
$accessToken = $_ENV['FACEBOOK_TEST_ACCESS_TOKEN'] ?? null;

if (!$appId || !$appSecret) {
    log_error("Missing FACEBOOK_APP_ID or FACEBOOK_APP_SECRET in .env");
    exit(1);
}

if (!$accessToken) {
    log_error("FACEBOOK_TEST_ACCESS_TOKEN not set in .env");
    echo "  Generate one at: https://developers.facebook.com/tools/explorer/" . PHP_EOL;
    echo "  Required permissions: pages_show_list, pages_read_engagement, pages_manage_engagement" . PHP_EOL;
    exit(1);
}

log_success("Loaded credentials from .env");
echo "  App ID: $appId" . PHP_EOL;
echo "  Graph Version: $graphVersion" . PHP_EOL;
echo PHP_EOL;

// Step 1: Get user's pages
log_info("Step 1: Fetching User's Facebook Pages");

$pagesUrl = "https://graph.facebook.com/$graphVersion/me/accounts";
$pagesResponse = file_get_contents("$pagesUrl?access_token=$accessToken");
$pagesData = json_decode($pagesResponse, true);

if (isset($pagesData['error'])) {
    log_error("Failed to fetch pages");
    echo json_encode($pagesData['error'], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}

$pages = $pagesData['data'] ?? [];
if (empty($pages)) {
    log_error("No pages found for this user");
    exit(1);
}

log_success("Found " . count($pages) . " page(s)");
foreach ($pages as $page) {
    echo "  - {$page['name']} (ID: {$page['id']})" . PHP_EOL;
}
echo PHP_EOL;

// Use first page
$firstPage = $pages[0];
$pageId = $firstPage['id'];
$pageName = $firstPage['name'];
$pageAccessToken = $firstPage['access_token'];

log_info("Using page: " . GREEN . $pageName . NC . " (ID: $pageId)");
echo PHP_EOL;

// Step 2: Fetch reviews
log_info("Step 2: Fetching Reviews from Facebook Page");

$reviewsUrl = "https://graph.facebook.com/$graphVersion/$pageId/ratings";
$reviewsParams = http_build_query([
    'access_token' => $pageAccessToken,
    'fields' => 'rating,review_text,recommendation_type,created_time,open_graph_story,reviewer{name}',
]);

$reviewsResponse = @file_get_contents("$reviewsUrl?$reviewsParams");
if ($reviewsResponse === false) {
    log_error("Failed to fetch reviews (network error)");
    exit(1);
}

$reviewsData = json_decode($reviewsResponse, true);

if (isset($reviewsData['error'])) {
    log_error("Failed to fetch reviews");
    echo json_encode($reviewsData['error'], JSON_PRETTY_PRINT) . PHP_EOL;
    log_warning("Note: If you see 'Unsupported get request', this page may not have reviews enabled.");
    exit(1);
}

$reviews = $reviewsData['data'] ?? [];
$reviewCount = count($reviews);

log_success("Found $reviewCount review(s)");
echo PHP_EOL;

if ($reviewCount === 0) {
    log_warning("No reviews found on this page");
    echo "  You can manually create a review to test response publishing." . PHP_EOL;
    exit(0);
}

// Display reviews
log_info("Recent reviews:");
foreach (array_slice($reviews, 0, 5) as $review) {
    $rating = $review['rating'];
    $author = $review['reviewer']['name'] ?? 'Anonymous';
    $text = $review['review_text'] ?? $review['recommendation_type'] ?? 'No text';
    echo "  ⭐ $rating/5 - $author: $text" . PHP_EOL;
}
echo PHP_EOL;

// Step 3: Test response publishing
log_info("Step 3: Testing Response Publishing");

$firstReview = $reviews[0];
$ratingId = $firstReview['open_graph_story']['id'] ?? null;

if (!$ratingId) {
    log_warning("Cannot find rating ID for first review (may not have open_graph_story)");
    echo "  Skipping response test." . PHP_EOL;
} else {
    echo "  Rating ID: $ratingId" . PHP_EOL;

    $testComment = "Thank you for your feedback! (Test response from Clarity SEO - PHP)";
    echo "  Posting test response..." . PHP_EOL;

    $responseUrl = "https://graph.facebook.com/$graphVersion/$pageId/ratings/$ratingId";

    $postData = http_build_query([
        'access_token' => $pageAccessToken,
        'comment' => $testComment,
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $postData,
        ],
    ]);

    $responseResult = @file_get_contents($responseUrl, false, $context);
    if ($responseResult === false) {
        log_error("Failed to post response (network error)");
    } else {
        $result = json_decode($responseResult, true);

        if (isset($result['success']) && $result['success']) {
            log_success("Successfully posted response to review!");
            echo "  Response: \"$testComment\"" . PHP_EOL;
        } elseif (isset($result['error'])) {
            log_error("Failed to post response");
            echo "  Error ({$result['error']['code']}): {$result['error']['message']}" . PHP_EOL;
            echo PHP_EOL;
            log_warning("Common issues:");
            echo "  - Missing 'pages_manage_engagement' permission" . PHP_EOL;
            echo "  - Page is not eligible for reviews" . PHP_EOL;
            echo "  - Token has expired" . PHP_EOL;
        } else {
            log_warning("Unexpected response:");
            echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
        }
    }
}

echo PHP_EOL;
log_info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
log_success("Integration test completed");
log_info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
echo PHP_EOL;

log_info("Summary:");
echo "  ✓ Loaded credentials from .env" . PHP_EOL;
echo "  ✓ Verified Facebook API connection" . PHP_EOL;
echo "  ✓ Fetched user pages" . PHP_EOL;
echo "  ✓ Retrieved reviews ($reviewCount found)" . PHP_EOL;
if ($ratingId) {
    echo "  ✓ Tested response publishing" . PHP_EOL;
}
echo PHP_EOL;
