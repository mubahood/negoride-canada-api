<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Encore\Admin\Auth\Database\Administrator;

echo "🔍 Checking online drivers\n\n";

$onlineDrivers = Administrator::where('ready_for_trip', 'Yes')
    ->where('status', 1)
    ->get();

echo "📊 Total drivers with ready_for_trip='Yes' and status=1: " . $onlineDrivers->count() . "\n\n";

foreach ($onlineDrivers as $driver) {
    echo "Driver ID: {$driver->id}\n";
    echo "  Name: {$driver->name}\n";
    echo "  Status: {$driver->status}\n";
    echo "  Ready for trip: {$driver->ready_for_trip}\n";
    echo "  Current address: " . ($driver->current_address ?? 'NULL') . "\n";
    echo "  User type: {$driver->user_type}\n";
    echo "  is_car: {$driver->is_car} | is_car_approved: {$driver->is_car_approved}\n";
    echo "  is_boda: {$driver->is_boda} | is_boda_approved: {$driver->is_boda_approved}\n";
    echo "  is_delivery: {$driver->is_delivery} | is_delivery_approved: {$driver->is_delivery_approved}\n";
    echo "  is_breakdown: {$driver->is_breakdown} | is_breakdown_approved: {$driver->is_breakdown_approved}\n";
    echo "  is_ambulance: {$driver->is_ambulance} | is_ambulance_approved: {$driver->is_ambulance_approved}\n";
    echo "  Updated at: {$driver->updated_at}\n";
    echo "\n";
}

echo "\n🔍 Checking for automobile='car' (Special Car)\n";
$carDrivers = Administrator::where('ready_for_trip', 'Yes')
    ->where('status', 1)
    ->where('is_car', 'Yes')
    ->where('is_car_approved', 'Yes')
    ->get();
echo "Found {$carDrivers->count()} car drivers\n\n";
