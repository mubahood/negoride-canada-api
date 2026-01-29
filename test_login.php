<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Encore\Admin\Auth\Database\Administrator;

echo "🧪 Testing Login Logic\n\n";

$testPhones = [
    '+17832946655',  // Correct number
    '+17832046655',  // Wrong number (what user is entering)
    '17832946655',
    '7832946655',
];

foreach ($testPhones as $phone) {
    echo "Testing: $phone\n";
    
    $identifier = trim($phone);
    $normalizedIdentifier = preg_replace('/[\s\-\(\)]/', '', $identifier);
    
    echo "  Normalized: $normalizedIdentifier\n";
    
    // Exact match test
    $u = Administrator::where('phone_number', $identifier)
        ->orWhere('username', $identifier)
        ->first();
    
    if ($u) {
        echo "  ✅ Found via exact match: ID {$u->id}, Name: {$u->name}\n";
    } else {
        echo "  ❌ Not found via exact match\n";
        
        // Try normalized search
        if (preg_match('/^\+?\d+$/', $normalizedIdentifier)) {
            $u = Administrator::whereRaw('REPLACE(REPLACE(REPLACE(REPLACE(phone_number, " ", ""), "-", ""), "(", ""), ")", "") = ?', [$normalizedIdentifier])
                ->orWhereRaw('REPLACE(REPLACE(REPLACE(REPLACE(username, " ", ""), "-", ""), "(", ""), ")", "") = ?', [$normalizedIdentifier])
                ->first();
            
            if ($u) {
                echo "  ✅ Found via normalized search: ID {$u->id}, Name: {$u->name}, Phone: {$u->phone_number}\n";
            } else {
                echo "  ❌ Not found via normalized search either\n";
            }
        }
    }
    echo "\n";
}

echo "\n💡 THE ISSUE:\n";
echo "The user is entering: 7832046655 (with a 0)\n";
echo "But the database has: 7832946655 (with a 9)\n";
echo "They are entering the WRONG phone number!\n";
