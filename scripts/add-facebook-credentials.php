#!/usr/bin/env php
<?php

/**
 * Add Facebook Page Credentials
 * 
 * Usage: php scripts/add-facebook-credentials.php YOUR_ACCESS_TOKEN
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "  Add Facebook Page Credentials\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

// Get access token from command line
$accessToken = $argv[1] ?? null;

if (!$accessToken) {
    echo "❌ Missing access token!\n";
    echo "\n";
    echo "Usage: php scripts/add-facebook-credentials.php YOUR_ACCESS_TOKEN\n";
    echo "\n";
    echo "To get an access token:\n";
    echo "1. Go to: https://developers.facebook.com/tools/explorer/\n";
    echo "2. Select your app (ID: " . env('FACEBOOK_APP_ID') . ")\n";
    echo "3. Add permissions:\n";
    echo "   - pages_show_list\n";
    echo "   - pages_read_engagement\n";
    echo "   - pages_manage_engagement\n";
    echo "4. Click 'Generate Access Token'\n";
    echo "5. Copy the token and run this script\n";
    echo "\n";
    exit(1);
}

echo "✓ Access token provided\n";
echo "\n";

// Get tenant
$tenant = App\Models\Tenant::first();
if (!$tenant) {
    echo "❌ No tenant found. Run: php scripts/setup-test-data.php\n";
    exit(1);
}

echo "✓ Using tenant: {$tenant->name}\n";
echo "\n";

// Fetch user's Facebook pages
echo "📥 Fetching your Facebook pages...\n";

$facebookService = app(App\Services\Listing\FacebookService::class);
$pages = $facebookService->getPages($accessToken);

if (!$pages) {
    echo "❌ Failed to fetch pages. Token may be invalid.\n";
    exit(1);
}

if (empty($pages)) {
    echo "⚠️  No pages found for this user.\n";
    exit(1);
}

echo "✓ Found " . count($pages) . " page(s):\n";
echo "\n";

foreach ($pages as $index => $page) {
    echo "  [" . ($index + 1) . "] {$page['name']} (ID: {$page['id']})\n";
}
echo "\n";

// Let user select a page
echo "Which page do you want to use? [1]: ";
$selection = trim(fgets(STDIN));
$selection = empty($selection) ? 1 : (int) $selection;

if ($selection < 1 || $selection > count($pages)) {
    echo "❌ Invalid selection\n";
    exit(1);
}

$selectedPage = $pages[$selection - 1];
echo "\n";
echo "✓ Selected: {$selectedPage['name']}\n";
echo "\n";

// Store credentials
echo "💾 Storing credentials...\n";

try {
    $credential = $facebookService->storeCredentials(
        $tenant,
        $accessToken,
        $selectedPage['id'],
        $selectedPage['access_token'],
        ['pages_show_list', 'pages_read_engagement', 'pages_manage_engagement']
    );

    echo "✅ Credentials stored!\n";
    echo "\n";
    echo "📊 Details:\n";
    echo "  Credential ID: {$credential->id}\n";
    echo "  Page ID: {$credential->getPageId()}\n";
    echo "  Platform: {$credential->platform}\n";
    echo "  Active: " . ($credential->is_active ? 'Yes' : 'No') . "\n";
    echo "\n";

    // Update location to use this page
    $location = App\Models\Location::where('tenant_id', $tenant->id)->first();
    if ($location) {
        $location->update(['facebook_page_id' => $selectedPage['id']]);
        echo "✓ Updated location '{$location->name}' to use this page\n";
        echo "\n";
    }

    echo "🎉 All set! Now run:\n";
    echo "   php scripts/test-facebook-response.php\n";
    echo "\n";

} catch (Exception $e) {
    echo "❌ Failed to store credentials: {$e->getMessage()}\n";
    exit(1);
}
