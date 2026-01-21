#!/usr/bin/env php
<?php

/**
 * Setup Test Data for Facebook Testing
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n";
echo "Setting up test data...\n";
echo "\n";

// Create or get user
$user = App\Models\User::first();
if (!$user) {
    $user = App\Models\User::factory()->create([
        'email' => 'test@clarity.com',
        'name' => 'Test User',
    ]);
    echo "✓ Created user: {$user->email}\n";
} else {
    echo "✓ User exists: {$user->email}\n";
}

// Create or get tenant
$tenant = App\Models\Tenant::first();
if (!$tenant) {
    $tenant = App\Models\Tenant::create(['name' => 'Test Company']);
    $tenant->users()->attach($user, ['role' => 'owner']);
    $user->update(['current_tenant_id' => $tenant->id]);
    echo "✓ Created tenant: {$tenant->name}\n";
} else {
    echo "✓ Tenant exists: {$tenant->name}\n";
}

// Create or get location
$location = App\Models\Location::where('tenant_id', $tenant->id)->first();
if (!$location) {
    $location = App\Models\Location::create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Store',
        'address' => '123 Main St',
    ]);
    echo "✓ Created location: {$location->name}\n";
} else {
    echo "✓ Location exists: {$location->name}\n";
}

echo "\n";
echo "✅ Setup complete!\n";
echo "\n";
echo "Tenant ID: {$tenant->id}\n";
echo "Location ID: {$location->id}\n";
echo "User ID: {$user->id}\n";
echo "\n";
echo "Next: Add Facebook credentials via API or update the database\n";
echo "\n";
