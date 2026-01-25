<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Review\ReviewService;
use App\Models\Location;
use App\Models\Listing;
use App\Models\PlatformCredential;
use Illuminate\Support\Facades\Http;
use App\Models\Review;

echo "--- Testing Review Saving FALLBACK Logic (Mock Mode) ---\n";

$loc = Location::first();
$locationName = 'locations/123456789';

// 1. SIMULATE BROKEN LINK: Delete the Listing record
Listing::where('location_id', $loc->id)->delete();
echo "1. Deleted Listing record (Simulating incomplete setup)\n";

// 2. SETUP CREDENTIAL: Ensure metadata has the location name
$cred = PlatformCredential::getForTenant($loc->tenant, PlatformCredential::PLATFORM_GOOGLE_MY_BUSINESS);
if (!$cred) {
    echo "Error: No credential found. Run previous setup first.\n";
    exit(1);
}

// Ensure metadata has location_name
$metadata = $cred->metadata ?? [];
$metadata['location_name'] = $locationName;
$cred->metadata = $metadata;
$cred->save();
echo "2. Updated Credential metadata with location_name: $locationName\n";

// 3. MOCK GOOGLE API
$mockReviewData = [
    'reviews' => [
        [
            'name' => $locationName . '/reviews/fallback-review-1',
            'starRating' => 'FIVE',
            'comment' => 'This review was saved via the FALLBACK mechanism! Great job.',
            'createTime' => now()->toIso8601String(),
            'reviewer' => [
                'displayName' => 'Fallback Tester',
                'profilePhotoUrl' => 'https://example.com/photo.jpg',
            ]
        ]
    ]
];

Http::fake(function ($request) use ($mockReviewData, $locationName) {
    // We expect the URL to use the location name from metadata
    if (str_contains($request->url(), $locationName)) {
        echo ">> HTTP REQUEST INTERCEPTED: " . $request->url() . "\n";
        return Http::response($mockReviewData, 200);
    }
    return Http::response([], 200);
});

// 4. RUN SYNC
echo "3. Running Sync...\n";
$service = app(ReviewService::class);
$counts = $service->syncReviewsForLocation($loc);

echo "Sync completed. Count: " . ($counts['google'] ?? 0) . "\n";

// 5. VERIFY
$review = Review::where('content', 'like', '%FALLBACK%')->first();

if ($review) {
    echo "\nSUCCESS! Review saved via fallback logic:\n";
    echo " - Author: " . $review->author_name . "\n";
    echo " - Content: " . $review->content . "\n";
} else {
    echo "\nFAILED. Review not found in database.\n";
}
