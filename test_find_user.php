<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Encore\Admin\Auth\Database\Administrator;

echo "🔍 Searching for user with phone: +17832046655\n\n";

// Search with different variations
$variations = [
    '+17832046655',
    '17832046655',
    '7832046655',
    '+1 783 204 6655',
    '+1-783-204-6655',
];

echo "Testing exact matches:\n";
foreach ($variations as $phone) {
    $user = Administrator::where('phone_number', $phone)->first();
    if ($user) {
        echo "✅ FOUND with: $phone\n";
        echo "   User ID: {$user->id}\n";
        echo "   Name: {$user->name}\n";
        echo "   Stored Phone: {$user->phone_number}\n";
        echo "   Username: {$user->username}\n";
        echo "   Email: {$user->email}\n";
        echo "   Status: {$user->status}\n\n";
    } else {
        echo "❌ Not found with: $phone\n";
    }
}

echo "\n📊 All users with phone numbers containing '783':\n";
$users = Administrator::where('phone_number', 'LIKE', '%783%')->get();
foreach ($users as $user) {
    echo "  ID: {$user->id} | Phone: {$user->phone_number} | Username: {$user->username} | Name: {$user->name}\n";
}

echo "\n📊 All users with phone numbers containing '204':\n";
$users = Administrator::where('phone_number', 'LIKE', '%204%')->get();
foreach ($users as $user) {
    echo "  ID: {$user->id} | Phone: {$user->phone_number} | Username: {$user->username} | Name: {$user->name}\n";
}

echo "\n📊 Last 10 registered users:\n";
$users = Administrator::orderBy('created_at', 'desc')->limit(10)->get();
foreach ($users as $user) {
    echo "  ID: {$user->id} | Phone: {$user->phone_number} | Created: {$user->created_at} | Name: {$user->name}\n";
}
