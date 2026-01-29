<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Encore\Admin\Auth\Database\Administrator;

echo "🧪 Testing Driver Search with Reduced Restrictions\n\n";

// Simulate customer search
$automobileFieldKey = 'is_car';
$automobileFieldValue = 'is_car_approved';

echo "Searching for: $automobileFieldKey = Yes\n";
echo "Approval field: $automobileFieldValue\n\n";

$drivers = Administrator::where('status', 1)
    ->where('ready_for_trip', 'Yes')
    ->whereNotNull('current_address') 
    ->where($automobileFieldKey, 'Yes')
    ->where(function($query) use ($automobileFieldValue) {
        // Allow both approved drivers AND drivers pending approval
        $query->where($automobileFieldValue, 'Yes')
              ->orWhere($automobileFieldValue, 'No');
    })
    ->limit(1000)
    ->orderBy('updated_at', 'desc')
    ->get();

echo "✅ Found {$drivers->count()} drivers with new logic (reduced restrictions)\n\n";

foreach ($drivers as $driver) {
    echo "Driver: {$driver->name} (ID: {$driver->id})\n";
    echo "  $automobileFieldKey: {$driver->$automobileFieldKey}\n";
    echo "  $automobileFieldValue: {$driver->$automobileFieldValue}\n";
    echo "  Location: {$driver->current_address}\n\n";
}

// Also test for 'boda'
echo "\n🧪 Testing Boda Search\n\n";
$automobileFieldKey = 'is_boda';
$automobileFieldValue = 'is_boda_approved';

$drivers = Administrator::where('status', 1)
    ->where('ready_for_trip', 'Yes')
    ->whereNotNull('current_address') 
    ->where($automobileFieldKey, 'Yes')
    ->where(function($query) use ($automobileFieldValue) {
        $query->where($automobileFieldValue, 'Yes')
              ->orWhere($automobileFieldValue, 'No');
    })
    ->limit(1000)
    ->orderBy('updated_at', 'desc')
    ->get();

echo "✅ Found {$drivers->count()} boda drivers\n";
