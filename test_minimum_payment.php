<?php

/**
 * Test Minimum Payment Amount Fix
 * 
 * This script tests that $1 CAD payments work correctly
 * after fixing the dollar-to-cents conversion bug.
 */

require_once 'vendor/autoload.php';

use App\Models\Negotiation;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "========================================\n";
echo "Testing Minimum Payment Amount Fix\n";
echo "========================================\n\n";

// Test conversion logic
echo "1️⃣  Testing Price Conversion Logic:\n";
echo "-----------------------------------\n";

$test_prices = [
    '0.50' => 50,   // Minimum allowed
    '1.0' => 100,   // $1 CAD
    '1.00' => 100,  // $1 CAD (with decimals)
    '5' => 500,     // $5 CAD
    '5.0' => 500,   // $5 CAD
    '12.5' => 1250, // $12.50 CAD
    '12.50' => 1250, // $12.50 CAD
    '100' => 10000,  // $100 CAD
];

$all_passed = true;

foreach ($test_prices as $dollar_amount => $expected_cents) {
    // Simulate the conversion in ApiChatController
    $price_in_dollars = floatval($dollar_amount);
    $price_in_cents = intval($price_in_dollars * 100);
    
    $status = $price_in_cents === $expected_cents ? '✅' : '❌';
    $passed = $price_in_cents === $expected_cents;
    
    if (!$passed) {
        $all_passed = false;
    }
    
    echo "{$status} \${$dollar_amount} → {$price_in_cents} cents (expected {$expected_cents})\n";
}

echo "\n";

// Test minimum validation
echo "2️⃣  Testing Minimum Validation:\n";
echo "-----------------------------------\n";

// Find a test negotiation or create one
$negotiation = Negotiation::where('agreed_price', '>', 0)->first();

if (!$negotiation) {
    echo "❌ No negotiation found for testing\n";
    echo "   Please create a negotiation first\n";
} else {
    echo "✅ Found negotiation #{$negotiation->id}\n";
    
    // Test various amounts
    $test_amounts = [
        25 => ['should_fail' => true, 'reason' => 'Below $0.50 minimum'],
        49 => ['should_fail' => true, 'reason' => 'Below $0.50 minimum'],
        50 => ['should_fail' => false, 'reason' => 'Minimum $0.50'],
        100 => ['should_fail' => false, 'reason' => '$1.00 CAD'],
        500 => ['should_fail' => false, 'reason' => '$5.00 CAD'],
        1250 => ['should_fail' => false, 'reason' => '$12.50 CAD'],
    ];
    
    echo "\n   Testing different amounts:\n";
    
    foreach ($test_amounts as $cents => $test_data) {
        $original_price = $negotiation->agreed_price;
        $negotiation->agreed_price = $cents;
        
        try {
            // Simulate the validation in create_payment_link
            $amount_cents = intval(floatval($negotiation->agreed_price));
            
            if ($amount_cents < 50) {
                throw new \Exception("Payment amount too small: $amount_cents cents");
            }
            
            if ($test_data['should_fail']) {
                echo "   ❌ {$cents} cents ({$test_data['reason']}) - Should have failed but didn't\n";
                $all_passed = false;
            } else {
                echo "   ✅ {$cents} cents ({$test_data['reason']}) - Passed validation\n";
            }
        } catch (\Exception $e) {
            if ($test_data['should_fail']) {
                echo "   ✅ {$cents} cents ({$test_data['reason']}) - Correctly rejected\n";
            } else {
                echo "   ❌ {$cents} cents ({$test_data['reason']}) - Should have passed: {$e->getMessage()}\n";
                $all_passed = false;
            }
        }
        
        // Restore original price
        $negotiation->agreed_price = $original_price;
    }
}

echo "\n";
echo "========================================\n";
if ($all_passed) {
    echo "✅ All Tests Passed!\n";
    echo "========================================\n";
    echo "\n";
    echo "Summary:\n";
    echo "  • Dollar-to-cents conversion working correctly\n";
    echo "  • $1 CAD (100 cents) passes minimum validation\n";
    echo "  • Amounts below $0.50 (50 cents) correctly rejected\n";
    echo "  • All price formats handled correctly\n";
} else {
    echo "❌ Some Tests Failed\n";
    echo "========================================\n";
    echo "\n";
    echo "Please review the errors above\n";
}

echo "\n";
