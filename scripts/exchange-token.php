<?php

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// The latest token you provided
$userToken = 'EAAJXrZC5KUZCcBQlBPtlnhBKwMhllExwTl2e71qe69xjEUS6FEZAQX2XXZAsJOkWKEGmb3fhwqcKjMR5WXTVtfpBEImqbihYKGgCZBzv9oZAlEMZBUylBdVvos4fUDCIaHJcAXaHNozXgxRN5SlesuEp9wqXkb4EtwQw55PoZBSSRq6pOZAqZC6lfgNycIc1alwYHnFjoZBftHPFMjRnRIuYkFUdZCt1xrzGZAWWgL21I3wZDZD';

echo "Fetching all pages associated with this token...\n";

$url = "https://graph.facebook.com/v24.0/me/accounts";

$response = Http::get($url, [
    'access_token' => $userToken
]);

if ($response->successful()) {
    $data = $response->json('data');
    if (empty($data)) {
        echo "No pages found. Make sure the token has 'pages_show_list' permission.\n";
    }
    foreach ($data as $page) {
        echo "----------------------------------------\n";
        echo "Page Name: " . $page['name'] . "\n";
        echo "Page ID: " . $page['id'] . "\n";
        echo "Page Token: " . $page['access_token'] . "\n";
        echo "Category: " . ($page['category'] ?? 'N/A') . "\n";
    }
} else {
    echo "❌ Error: " . $response->status() . "\n";
    print_r($response->json());
}
