<?php

require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

$packageName = $argv[1] ?? 'acada.app.acada';

echo "Attempting to fetch reviews unofficially for: $packageName\n";

$client = new Client([
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Referer' => "https://play.google.com/store/apps/details?id=$packageName",
        'Origin' => 'https://play.google.com',
        'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
    ],
    'cookies' => true,
]);

// Step 1: Request the main page to get any necessary cookies/tokens (though batchexecute often works without strict tokens for public data)
try {
    $client->get("https://play.google.com/store/apps/details?id=$packageName&hl=en&gl=US");
} catch (\Exception $e) {
    echo "Warning: Failed to load main page: " . $e->getMessage() . "\n";
}

// RPC ID for fetching reviews is often 'Uvr5Mg'
// Structure: [packageName, null, 2, sortOrder, [maxResults, null, paginationToken], null, [], []]
// Sort Order: 2 (Newest), 1 (Most Helpful), etc.
$rpcId = 'Uvr5Mg'; 
$payload = json_encode([
    $packageName,
    null,
    2, // Sort by newest
    null,
    [10, null, null], // Fetch 10 reviews
    null, 
    [], 
    []
]);

// The batchexecute format is: f.req=[[["RPC_ID","ESCAPED_PAYLOAD",null,"generic"]]]
$fReq = json_encode([
    [
        [$rpcId, $payload, null, "generic"]
    ]
]);

try {
    $response = $client->post('https://play.google.com/_/PlayStoreUi/data/batchexecute?rpcids=' . $rpcId . '&f.sid=-7887640626359006093&bl=boq_playuiserver_20240116.06_p0&hl=en&gl=US&authuser=0&soc-app=121&soc-platform=1&soc-device=1', [
        'form_params' => [
            'f.req' => $fReq,
        ]
    ]);

    $body = (string) $response->getBody();
    
    // The response starts with )]}'\n and then JSON lines.
    // We need to look for the payload inside.
    
    // Extract the inner JSON
    preg_match('/["|"]wrb.fr["|"],["|"]' . $rpcId . '["|"],["|"](.*?)["|"],/s', $body, $matches);
    
    if (isset($matches[1])) {
        $innerJson = json_decode(base64_decode($matches[1]), true); // Wait, usually it's just JSON string, not base64?
        // Actually batchexecute returns a JSON array where one element is the data string.
        
        // Let's try to just dump the raw body first to see structure if regex fails or parsing is complex
        // Google's response is messy.
        
        // Try to parse the main response container
        $cleanBody = substr($body, strpos($body, "\n") + 1); // Skip )]}'
        $responseJson = json_decode($cleanBody, true);
        
        if ($responseJson && isset($responseJson[0][2])) {
            $dataString = $responseJson[0][2];
            $data = json_decode($dataString, true);
            
            // Navigate the specific messy array structure of Google Play Reviews
            // This structure changes often, so we explore.
            if (isset($data[0])) {
                $reviewsRaw = $data[0];
                echo "Found " . count($reviewsRaw) . " potential reviews.\n";
                
                foreach ($reviewsRaw as $item) {
                    // Structure usually: [reviewId, content, rating, timestamp, author, ...]
                    // Check identifying fields
                    if (is_array($item)) {
                        $reviewId = $item[0];
                        $content = $item[4];
                        $rating = $item[2];
                        $author = $item[1][0];
                        $date = $item[5][0];
                        
                        echo "------------------------------------------------\n";
                        echo "Review ID: gp:$reviewId\n"; // Usually IDs in API don't have 'gp:' prefix in raw data? Or they do?
                        // Actually, raw web IDs often look like "gp:AOqp..." or just UUIDs.
                        // Let's print identifying info.
                        echo "Author: $author\n";
                        echo "Rating: $rating\n";
                        echo "Date: $date\n";
                        echo "Content: " . substr($content, 0, 50) . "...\n";
                        
                        echo "\nTo test reply, run:\n";
                        echo "php scripts/test-google-play.php $packageName \"gp:$reviewId\"\n";
                        // Note: If ID doesn't start with gp:, we might need to prepend it, or maybe it's a different format.
                    }
                }
            } else {
                echo "Parsed data but didn't find reviews array at index 0.\n";
                 // print_r($data); // Uncomment to debug
            }
        } else {
            echo "Could not parse inner data string.\n";
            echo "Raw Body Preview: " . substr($body, 0, 500) . "\n";
        }
    } else {
        echo "Could not match RPC ID in response.\n";
        // Attempt fallback parsing
        $cleanBody = substr($body, strpos($body, "\n") + 1);
        $responseJson = json_decode($cleanBody, true);
        if ($responseJson) {
             $dataString = $responseJson[0][2] ?? null;
             if ($dataString) {
                 $data = json_decode($dataString, true);
                 if ($data && isset($data[0])) {
                     echo "Found " . count($data[0]) . " reviews via fallback parsing!\n";
                     foreach ($data[0] as $item) {
                         $reviewId = $item[0];
                         echo "ID: $reviewId\n";
                     }
                 }
             }
        } else {
             echo "Raw Body Preview: " . substr($body, 0, 500) . "\n";
        }
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
