#!/usr/bin/env php
<?php

/**
 * Facebook Review Response Test Script
 * Tests the full flow: Sync reviews → Create response → Publish to Facebook
 * 
 * Usage: php scripts/test-facebook-response.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Foundation\Application;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  Facebook Review Response Test\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

// Step 1: Get tenant and location
$tenant = App\Models\Tenant::first();
if (!$tenant) {
    echo "❌ No tenant found. Please create a tenant first.\n";
    exit(1);
}

$location = App\Models\Location::where('tenant_id', $tenant->id)->first();
if (!$location) {
    echo "❌ No location found. Please create a location first.\n";
    exit(1);
}

echo "✓ Using Tenant: {$tenant->name}\n";
echo "✓ Using Location: {$location->name}\n";
echo "\n";

// Step 2: Check for Facebook credentials
$credential = App\Models\PlatformCredential::where('tenant_id', $tenant->id)
    ->where('platform', 'facebook')
    ->where('is_active', true)
    ->first();

if (!$credential) {
    echo "❌ No Facebook credentials found.\n";
    echo "   Please connect a Facebook page first via the API.\n";
    exit(1);
}

echo "✓ Facebook credential found\n";
echo "  Page ID: " . $credential->getPageId() . "\n";
echo "\n";

// Step 3: Sync reviews from Facebook
echo "📥 Syncing Facebook reviews...\n";
$reviewService = app(App\Services\Review\ReviewService::class);

try {
    $counts = $reviewService->syncReviewsForLocation($location);
    echo "✓ Synced {$counts['facebook']} Facebook reviews\n";
} catch (Exception $e) {
    echo "❌ Failed to sync reviews: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n";

// Step 4: Get a Facebook review to respond to
$facebookReview = App\Models\Review::where('location_id', $location->id)
    ->where('platform', 'facebook')
    ->whereDoesntHave('response')
    ->latest()
    ->first();

if (!$facebookReview) {
    echo "⚠️  No Facebook reviews found without responses.\n";
    echo "   Either sync more reviews or all reviews already have responses.\n";
    exit(0);
}

echo "📝 Found review to respond to:\n";
echo "  Author: {$facebookReview->author_name}\n";
echo "  Rating: {$facebookReview->rating}/5\n";
echo "  Content: " . substr($facebookReview->content ?? 'No text', 0, 50) . "...\n";
echo "\n";

// Step 5: Create a test response
echo "💬 Creating response...\n";
$user = App\Models\User::where('current_tenant_id', $tenant->id)->first();
if (!$user) {
    $user = App\Models\User::first();
}

$responseContent = "Thank you for your feedback! We really appreciate your review. (Test from Clarity SEO - " . date('H:i:s') . ")";

$response = App\Models\ReviewResponse::create([
    'review_id' => $facebookReview->id,
    'user_id' => $user->id,
    'content' => $responseContent,
    'status' => 'approved',
]);

echo "✓ Response created: ID {$response->id}\n";
echo "  Content: {$responseContent}\n";
echo "\n";

// Step 6: Publish to Facebook
echo "📤 Publishing response to Facebook...\n";
echo "  Using endpoint: POST /{open_graph_story_id}/comments\n";
echo "  Open Graph Story ID: " . ($facebookReview->metadata['open_graph_story']['id'] ?? 'NOT FOUND') . "\n";
echo "\n";

try {
    $publishedResponse = $reviewService->publishResponse($response);

    if ($publishedResponse->isPublished()) {
        echo "✅ SUCCESS! Response published to Facebook!\n";
        echo "\n";
        echo "📊 Details:\n";
        echo "  Response ID: {$publishedResponse->id}\n";
        echo "  Published At: {$publishedResponse->published_at}\n";
        echo "  Platform Synced: " . ($publishedResponse->platform_synced ? 'Yes' : 'No') . "\n";
        echo "\n";
        echo "🎉 Go check Facebook to see your response!\n";
    } else {
        echo "❌ Response was not published.\n";
        echo "   Check the logs for details: storage/logs/laravel.log\n";
    }
} catch (Exception $e) {
    echo "❌ Failed to publish response: {$e->getMessage()}\n";
    echo "\n";
    echo "🔍 Common issues:\n";
    echo "  1. Missing permissions (need pages_manage_engagement)\n";
    echo "  2. Token expired (re-authorize the page)\n";
    echo "  3. Invalid open_graph_story ID in review metadata\n";
    echo "\n";
    echo "📝 Check logs: tail -f storage/logs/laravel.log\n";
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
