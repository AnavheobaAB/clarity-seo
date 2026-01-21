<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Models\PlatformCredential;
use App\Models\Review;
use App\Models\Location;
use App\Services\Review\YouTubeReviewService;
use Google\Client;
use Google\Service\YouTube;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;

// 1. Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- YouTube API Verification Script ---\n";

// 2. Setup Google Client
$client = new Client();
$client->setClientId(env('YOUTUBE_CLIENT_ID'));
$client->setClientSecret(env('YOUTUBE_CLIENT_SECRET'));
$client->setRedirectUri(env('YOUTUBE_REDIRECT_URI'));
$client->addScope(YouTube::YOUTUBE_FORCE_SSL);
$client->setAccessType('offline');
$client->setPrompt('select_account consent');

// 3. Handle OAuth Flow
$tokenFile = __DIR__ . '/../storage/app/youtube_test_token.json';

if (file_exists($tokenFile)) {
    $accessToken = json_decode(file_get_contents($tokenFile), true);
    $client->setAccessToken($accessToken);
}

if ($client->isAccessTokenExpired()) {
    if ($client->getRefreshToken()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
    } else {
        $authUrl = $client->createAuthUrl();
        echo "1. Open the following link in your browser:\n\n{$authUrl}\n\n";
        echo "2. After authorizing, you will be redirected to: " . env('YOUTUBE_REDIRECT_URI') . "\n";
        echo "3. Copy the 'code' parameter from the URL and paste it here: ";
        $authCode = trim(fgets(STDIN));

        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
        $client->setAccessToken($accessToken);
    }
    
    file_put_contents($tokenFile, json_encode($client->getAccessToken()));
    echo "Token saved to storage.\n";
}

// 4. Test API Calls
$youtube = new YouTube($client);
$service = new YouTubeReviewService();
$service->setYouTubeService($youtube);

echo "\n--- Step 1: Fetching Channel Data ---\n";
try {
    $response = $youtube->channels->listChannels('snippet,contentDetails,statistics', ['mine' => true]);
    if (empty($response->items)) {
        die("Error: No channels found for this account.\n");
    }
    $channel = $response->items[0];
    $channelId = $channel->id;
    echo "Connected to Channel: " . $channel->snippet->title . " (ID: {$channelId})\n";
} catch (Exception $e) {
    die("Error fetching channel: " . $e->getMessage() . "\n");
}

echo "\n--- Step 2: Syncing Comments (Mocking Location) ---\n";
// Create a temporary mock location for testing
$location = new Location([
    'youtube_channel_id' => $channelId,
    'name' => 'YouTube Test Location'
]);
$location->id = 9999; // Dummy ID

// We need a dummy credential object to satisfy the service method signature
$credential = new PlatformCredential([
    'access_token' => $client->getAccessToken()['access_token'],
    'refresh_token' => $client->getRefreshToken(),
]);

$count = $service->syncReviews($location, $credential);
echo "Successfully synced {$count} recent comments.\n";

if ($count > 0) {
    $latestReview = Review::where('platform', 'youtube')->orderBy('published_at', 'desc')->first();
    echo "Latest Comment: \"{$latestReview->content}\" by {$latestReview->author_name}\n";

    echo "\nDo you want to test replying to this comment? (y/n): ";
    $choice = trim(fgets(STDIN));

    if (strtolower($choice) === 'y') {
        echo "Enter your reply: ";
        $replyContent = trim(fgets(STDIN));
        
        echo "Sending reply...\n";
        $success = $service->replyToReview($latestReview, $replyContent, $credential);
        
        if ($success) {
            echo "✅ Reply posted successfully to YouTube!\n";
        } else {
            echo "❌ Failed to post reply.\n";
        }
    }
} else {
    echo "No comments found to test replying.\n";
}

echo "\nVerification complete.\n";


