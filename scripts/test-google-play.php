<?php

use App\Models\Location;
use App\Models\PlatformCredential;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Review\GooglePlayStoreService;
use App\Models\Review;
use Illuminate\Support\Facades\Log;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Ensure we have a tenant and user for testing
$user = User::first();
if (!$user) {
    echo "Creating test user...\n";
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
}

$tenant = Tenant::first();
if (!$tenant) {
    echo "Creating test tenant...\n";
    $tenant = Tenant::factory()->create(['name' => 'Test Tenant']);
    $user->tenants()->attach($tenant, ['role' => 'owner']);
}

// Helper to get package name from args
$packageName = $argv[1] ?? env('GOOGLE_PLAY_PACKAGE_NAME');
$manualReviewId = $argv[2] ?? null;
$automaticReply = $argv[3] ?? null;

if (!$packageName) {
    echo "Error: Please provide the Google Play Package Name (e.g. com.example.app) as the first argument.\n";
    echo "Usage: php scripts/test-google-play.php <package_name> [review_id] [reply_text]\n";
    exit(1);
}

echo "Using Package Name: $packageName\n";

// Load credentials
$credentialsPath = env('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON');

// If it's a relative path, make it absolute from storage
if (!str_starts_with($credentialsPath, '/') && !str_starts_with($credentialsPath, '{')) {
    $credentialsPath = base_path($credentialsPath);
}

if (!file_exists($credentialsPath) && !str_starts_with($credentialsPath, '{')) {
    echo "Error: Credentials file not found at $credentialsPath\n";
    exit(1);
}
$jsonContent = str_starts_with($credentialsPath, '{') ? $credentialsPath : file_get_contents($credentialsPath);

// Create or update a test location with these details
$location = Location::updateOrCreate(
    [
        'tenant_id' => $tenant->id,
        'name' => 'Google Play Test Location',
    ],
    [
        'google_play_package_name' => $packageName,
    ]
);

// Create or update the platform credential for this tenant
PlatformCredential::updateOrCreate(
    [
        'tenant_id' => $tenant->id,
        'platform' => PlatformCredential::PLATFORM_GOOGLE_PLAY,
    ],
    [
        'access_token' => $jsonContent,
        'is_active' => true,
    ]
);

echo "Syncing reviews...\n";

try {
    $service = app(GooglePlayStoreService::class);
    
    // Hack to access the protected publisher service or create a new one to debug
    $reflection = new \ReflectionClass($service);
    $method = $reflection->getMethod('getAndroidPublisherServiceForLocation');
    $method->setAccessible(true);
    $publisher = $method->invoke($service, $location);

    echo "Calling Google Play API...\n";
    $reviewsResponse = $publisher->reviews->listReviews($packageName);
    
    $reviews = $reviewsResponse->getReviews();
    $total = count($reviews ?? []);
    echo "Raw API returned $total reviews.\n";
    
    $count = $service->syncReviews($location);
    echo "Service Sync returned: $count\n";

    $targetReview = null;

    if ($count > 0) {
        $targetReview = Review::where('location_id', $location->id)
            ->where('platform', PlatformCredential::PLATFORM_GOOGLE_PLAY)
            ->latest('published_at')
            ->first();
    } elseif ($manualReviewId) {
        echo "Using manual ID: $manualReviewId\n";
        $targetReview = Review::updateOrCreate(
            [
                'location_id' => $location->id,
                'platform' => PlatformCredential::PLATFORM_GOOGLE_PLAY,
                'external_id' => $manualReviewId,
            ],
            [
                'author_name' => 'Manual Test User',
                'rating' => 5,
                'content' => 'Manual test review content',
                'published_at' => now(),
            ]
        );
    }

    if ($targetReview) {
        echo "\nTarget Review: {$targetReview->external_id}\n";

        $replyContent = $automaticReply;
        
        if (empty($replyContent)) {
             echo "No reply text provided. Skipping reply test.\n";
             echo "To test reply, run: php scripts/test-google-play.php $packageName {$targetReview->external_id} \"Your reply here\"\n";
        } else {
            echo "Sending reply: \"$replyContent\"\n";
            if ($service->replyToReview($targetReview, $replyContent)) {
                echo "SUCCESS: Reply published to Google Play Store!\n";
            } else {
                echo "FAILED: Check storage/logs/laravel.log for details.\n";
            }
        }
    } else {
        echo "No reviews found.\n";
    }

} catch (\Exception $e) {
    echo "API ERROR: " . $e->getMessage() . "\n";
}
