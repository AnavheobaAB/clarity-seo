#!/usr/bin/env php
<?php

/**
 * Test Facebook Response Publishing Without Syncing
 * Creates a fake review and tests posting response to Facebook
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  Test Facebook Response Publishing\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

// Get user input for open_graph_story ID
echo "To test response publishing, you need to manually get a review ID from Facebook.\n";
echo "\n";
echo "Steps:\n";
echo "1. Go to your Facebook Page: https://www.facebook.com/KeepProof\n";
echo "2. Click on 'Reviews' section\n";
echo "3. Find a review you want to test responding to\n";
echo "4. Come back here\n";
echo "\n";
echo "❓ Do you see any reviews on your Facebook page? (yes/no): ";
$hasReviews = trim(fgets(STDIN));

if (strtolower($hasReviews) !== 'yes') {
    echo "\n";
    echo "⚠️  Your page doesn't have reviews yet.\n";
    echo "   You need at least one review to test response publishing.\n";
    echo "   Ask someone to leave a review on your Facebook page first!\n";
    echo "\n";
    exit(0);
}

echo "\n";
echo "Great! Now I'll create a test review in the database.\n";
echo "You'll need to get the review's 'open_graph_story' ID from Facebook's API manually.\n";
echo "\n";

// For now, create a dummy review for testing
$tenant = App\Models\Tenant::first();
$location = App\Models\Location::first();
$user = App\Models\User::first();

if (!$tenant || !$location || !$user) {
    echo "❌ Missing tenant/location/user. Run setup script first.\n";
    exit(1);
}

echo "Enter the review's open_graph_story ID (or press Enter to skip): ";
$storyId = trim(fgets(STDIN));

if (empty($storyId)) {
    echo "\n";
    echo "⚠️  Without a real open_graph_story ID, we can't test the actual API.\n";
    echo "\n";
    echo "📚 To get the ID:\n";
    echo "   Run this in Graph API Explorer:\n";
    echo "   GET /573045705903114/ratings?fields=id,rating,review_text,open_graph_story\n";
    echo "\n";
    echo "   Then use theopenGraph_story.id from the response.\n";
    echo "\n";
    exit(0);
}

// Create test review
$review = App\Models\Review::create([
    'location_id' => $location->id,
    'platform' => 'facebook',
    'external_id' => $storyId,
    'author_name' => 'Test Reviewer',
    'rating' => 5,
    'content' => 'Great service!',
    'published_at' => now(),
    'metadata' => [
        'open_graph_story' => [
            'id' => $storyId
        ]
    ],
]);

echo "✓ Created test review (ID: {$review->id})\n";
echo "\n";

// Create response
$responseContent = "Thank you for your amazing review! We're glad you had a great experience. 🎉";

$response = App\Models\ReviewResponse::create([
    'review_id' => $review->id,
    'user_id' => $user->id,
    'content' => $responseContent,
    'status' => 'approved',
]);

echo "✓ Created response (ID: {$response->id})\n";
echo "  Content: {$responseContent}\n";
echo "\n";

// Publish to Facebook
echo "📤 Publishing to Facebook...\n";
echo "\n";

$reviewService = app(App\Services\Review\ReviewService::class);

try {
    $publishedResponse = $reviewService->publishResponse($response);

    if ($publishedResponse->isPublished()) {
        echo "✅ SUCCESS! Response published to Facebook!\n";
        echo "\n";
        echo "🎉 Go check your Facebook page to see the response!\n";
        echo "   https://www.facebook.com/KeepProof\n";
    } else {
        echo "❌ Response was not published.\n";
        echo "   Check logs: tail -f storage/logs/laravel.log\n";
    }
} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "\n";
    echo "Check logs for details: tail -f storage/logs/laravel.log\n";
}

echo "\n";
