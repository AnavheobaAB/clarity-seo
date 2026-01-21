<?php

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing Facebook Reply...\n";

$tenant = App\Models\Tenant::first();
$credential = App\Models\PlatformCredential::where('tenant_id', $tenant->id)
    ->where('platform', 'facebook')
    ->first();

$pageAccessToken = $credential->metadata['page_access_token'];
$storyId = '861212880122386'; // The ID we just found

echo "Replying to Story ID: $storyId\n";

$url = "https://graph.facebook.com/v24.0/{$storyId}/comments";

$response = Http::post($url, [
    'access_token' => $pageAccessToken,
    'message' => 'Thank you for your feedback! This is an automated reply from Clarity SEO testing the integration. 🚀'
]);

if ($response->successful()) {
    echo "✅ SUCCESS! Reply posted.\n";
    print_r($response->json());
} else {
    echo "❌ FAILED: " . $response->status() . "\n";
    print_r($response->json());
}


