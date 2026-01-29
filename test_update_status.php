<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Encore\Admin\Auth\Database\Administrator;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiAuthController;

echo "🧪 Testing update-online-status endpoint\n\n";

// Get a driver user
$driver = Administrator::where('ready_for_trip', 'Yes')->first();

if (!$driver) {
    echo "❌ No online drivers found for testing\n";
    exit;
}

echo "Testing with driver: {$driver->name} (ID: {$driver->id})\n";
echo "Current status: ready_for_trip = {$driver->ready_for_trip}\n\n";

// Test 1: Status change to offline (without GPS)
echo "Test 1: Going offline without GPS coordinates\n";
$request = Request::create('/api/update-online-status', 'POST', [
    'status' => 'offline',
]);
$request->setUserResolver(function() use ($driver) {
    return $driver;
});

$controller = new ApiAuthController();
try {
    $response = $controller->update_online_status($request);
    echo "Response: " . json_encode($response->getData()) . "\n\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Refresh driver
$driver = Administrator::find($driver->id);
echo "After going offline: ready_for_trip = {$driver->ready_for_trip}\n\n";

// Test 2: Status change to online (with GPS)
echo "Test 2: Going online with GPS coordinates\n";
$request = Request::create('/api/update-online-status', 'POST', [
    'status' => 'online',
    'latitude' => '0.3421',
    'longitude' => '32.6457',
]);
$request->setUserResolver(function() use ($driver) {
    return $driver;
});

try {
    $response = $controller->update_online_status($request);
    echo "Response: " . json_encode($response->getData()) . "\n\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Refresh driver
$driver = Administrator::find($driver->id);
echo "After going online: ready_for_trip = {$driver->ready_for_trip}\n";
echo "Location: {$driver->current_address}\n\n";

// Test 3: Get current status (no status parameter)
echo "Test 3: Get current status without changing it\n";
$request = Request::create('/api/update-online-status', 'POST', []);
$request->setUserResolver(function() use ($driver) {
    return $driver;
});

try {
    $response = $controller->update_online_status($request);
    echo "Response: " . json_encode($response->getData()) . "\n\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

echo "✅ Testing complete\n";
