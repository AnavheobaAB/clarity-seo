#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Debugging Facebook Reviews...\n\n";

$tenant = App\Models\Tenant::first();
$credential = App\Models\PlatformCredential::where('tenant_id', $tenant->id)
    ->where('platform', 'facebook')
    ->first();

if (!$credential) {
    echo "No Facebook credential found.\n";
    exit(1);
}

$pageId = $credential->getPageId();
$accessToken = $credential->metadata['page_access_token'] ?? $credential->access_token;

echo "Page ID: $pageId\n";
echo "Using Token from Database: " . substr($accessToken, 0, 15) . "...\n";

// Manual request to see raw output
$url = "https://graph.facebook.com/v24.0/{$pageId}/ratings";
echo "Fetching from: $url\n";

$response = Illuminate\Support\Facades\Http::get($url, [
    'access_token' => $accessToken,
    'fields' => 'rating,review_text,recommendation_type,created_time,open_graph_story,reviewer{name,picture}',
    'limit' => 5
]);

if ($response->successful()) {
    $data = $response->json('data');
    echo "Found " . count($data) . " reviews.\n\n";
    
    foreach ($data as $item) {
        echo "------------------------------------------------\n";
        echo "Author: " . ($item['reviewer']['name'] ?? 'Unknown') . "\n";
        echo "Rating: " . ($item['rating'] ?? 'N/A') . "\n";
        echo "Text: " . ($item['review_text'] ?? $item['recommendation_type'] ?? 'No text') . "\n";
        echo "Created: " . ($item['created_time'] ?? 'Unknown') . "\n";
        echo "OG Story ID: " . ($item['open_graph_story']['id'] ?? 'MISSING') . "\n";
    }
} else {
    echo "Error: " . $response->status() . "\n";
    print_r($response->json());
}
