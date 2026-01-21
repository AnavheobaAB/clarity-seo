<?php

use Google\Client;
use Google\Service\AndroidPublisher;

require __DIR__ . '/../vendor/autoload.php';

$keyPath = __DIR__ . '/../storage/app/private/kimdeo-credentials.json';
$packageName = $argv[1] ?? 'jat.sharer.com';

echo "Testing package name: $packageName\n";

$client = new Client();
$client->setAuthConfig($keyPath);
$client->addScope(AndroidPublisher::ANDROIDPUBLISHER);

$service = new AndroidPublisher($client);

try {
    // Try to create an edit to verify package existence/access
    $edit = $service->edits->insert($packageName, new \Google\Service\AndroidPublisher\AppEdit());
    echo "SUCCESS: Package '$packageName' is VALID and accessible by this service account.\n";
    echo "Edit ID: " . $edit->getId() . "\n";
    
    // Clean up by deleting the edit (optional but good practice)
    // $service->edits->delete($packageName, $edit->getId());
    
} catch (\Google\Service\Exception $e) {
    echo "ERROR: Google API Exception\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    
    $errors = json_decode($e->getMessage(), true)['error']['errors'] ?? [];
    foreach ($errors as $err) {
        echo "Reason: " . ($err['reason'] ?? 'unknown') . "\n";
        echo "Message: " . ($err['message'] ?? 'unknown') . "\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
