<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Review\ReviewService;
use App\Models\Location;
use App\Models\PlatformCredential;
use Illuminate\Support\Facades\Http;
use App\Models\Review;

echo "--- Testing Review Saving Logic (Mock Mode Debug) ---\\n";

// Debug 1: Check Credential
$loc = Location::first();
$cred = PlatformCredential::getForTenant($loc->tenant, PlatformCredential::PLATFORM_GOOGLE_MY_BUSINESS);

echo "Credential Found: " . ($cred ? 'Yes' : 'No') . "\\n";
if ($cred) {
    echo " - Valid: " . ($cred->isValid() ? 'Yes' : 'No') . "\\n";
    echo " - Token: " . substr($cred->access_token, 0, 10) . "...\\n";
    echo " - External ID: " . $cred->external_id . "\\n";
}

// Debug 2: Check Listing
$listing = \App\Models\Listing::where('location_id', $loc->id)->where('platform', 'google_my_business')->first();
echo "Listing Found: " . ($listing ? 'Yes' : 'No') . "\\n";
if ($listing) {
    echo " - External ID: " . $listing->external_id . "\\n";
}

// Setup Mock Data
$mockReviewData = [
    'reviews' => [
        [
            'name' => 'locations/123456789/reviews/mock-review-id-1',
            'starRating' => 'FIVE',
            'comment' => 'This is a mocked review! The system works perfectly.',
            'createTime' => '2026-01-25T12:00:00Z',
            'reviewer' => [
                'displayName' => 'Test User',
                'profilePhotoUrl' => 'https://example.com/photo.jpg',
            ]
        ]
    ]
];

// Fake Http
Http::fake(function ($request) use ($mockReviewData) {
    echo ">> HTTP REQUEST INTERCEPTED: " . $request->url() . "\\n";
    if (str_contains($request->url(), 'mybusiness.googleapis.com')) {
        return Http::response($mockReviewData, 200);
    }
    return Http::response([], 200);
});

// Run Sync
echo "Syncing reviews for Location: " . $loc->name . "\\n";
$service = app(ReviewService::class);
$counts = $service->syncReviewsForLocation($loc);

echo "Sync completed. Count from Service: " . ($counts['google'] ?? 0) . "\\n";

// Verify
$dbCount = Review::count();
echo "Total Reviews in Database: " . $dbCount . "\\n";

if ($dbCount > 0) {
    foreach (Review::all() as $review) {
        echo " - [{$review->rating} Stars] {$review->author_name}: \"{$review->content}\"\\n";
    }
}