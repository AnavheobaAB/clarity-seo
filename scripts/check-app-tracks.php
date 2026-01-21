<?php

use Google\Client;
use Google\Service\AndroidPublisher;

require __DIR__ . '/../vendor/autoload.php';

$keyPath = __DIR__ . '/../storage/app/private/kimdeo-credentials.json';
$packageName = $argv[1] ?? 'jat.sharer.com';

echo "Checking tracks for package: $packageName\n";

$client = new Client();
$client->setAuthConfig($keyPath);
$client->addScope(AndroidPublisher::ANDROIDPUBLISHER);

$service = new AndroidPublisher($client);

try {
    // Open an edit transaction
    $edit = $service->edits->insert($packageName, new \Google\Service\AndroidPublisher\AppEdit());
    $editId = $edit->getId();
    
    // List standard tracks
    $tracksToCheck = ['production', 'beta', 'alpha', 'internal'];
    
    $found = false;
    foreach ($tracksToCheck as $trackName) {
        try {
            $track = $service->edits_tracks->get($packageName, $editId, $trackName);
            $releases = $track->getReleases();
            
            if (!empty($releases)) {
                $found = true;
                echo "\nTrack: " . strtoupper($trackName) . "\n";
                foreach ($releases as $release) {
                    echo " - Version Code: " . $release->getVersionCodes()[0] . "\n";
                    echo " - Status: " . $release->getStatus() . "\n";
                    echo " - Name: " . ($release->getName() ?? '(no name)') . "\n";
                }
            } else {
                 echo "Track: " . strtoupper($trackName) . " - No releases found.\n";
            }
        } catch (\Exception $e) {
            // Track might not exist or other error
            echo "Track: " . strtoupper($trackName) . " - " . $e->getMessage() . "\n";
        }
    }

    if (!$found) {
        echo "\nNo active releases found in standard tracks.\n";
    }

    // Clean up
    // $service->edits->delete($packageName, $editId);

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
