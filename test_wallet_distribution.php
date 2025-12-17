<?php

/**
 * Wallet & Transaction Distribution System - Comprehensive Test
 * 
 * This script tests:
 * 1. Auto wallet creation when user is created
 * 2. Payment distribution (90% driver, 10% company)
 * 3. Transaction creation and linking
 * 4. Balance updates and integrity
 * 5. Idempotency (no duplicate distributions)
 */

require __DIR__ . '/vendor/autoload.php';

use App\Models\User;
use App\Models\UserWallet;
use App\Models\Negotiation;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n";
echo "=" . str_repeat("=", 78) . "=\n";
echo "  WALLET & TRANSACTION DISTRIBUTION SYSTEM - COMPREHENSIVE TEST\n";
echo "=" . str_repeat("=", 78) . "=\n";
echo "\n";

// Test counters
$testsRun = 0;
$testsPassed = 0;
$testsFailed = 0;

function test($description, $callback) {
    global $testsRun, $testsPassed, $testsFailed;
    $testsRun++;
    echo "[$testsRun] Testing: $description\n";
    
    try {
        $result = $callback();
        if ($result === true) {
            $testsPassed++;
            echo "    ✅ PASSED\n";
            return true;
        } else {
            $testsFailed++;
            echo "    ❌ FAILED: " . ($result ?: 'Unknown error') . "\n";
            return false;
        }
    } catch (Exception $e) {
        $testsFailed++;
        echo "    ❌ FAILED: " . $e->getMessage() . "\n";
        return false;
    }
}

function formatMoney($amount) {
    return '$' . number_format($amount, 2);
}

echo "🧹 Cleaning up test data...\n";
// Clean up any existing test data
DB::table('transactions')->where('negotiation_id', '>', 0)->delete();
DB::table('negotiations')->where('customer_id', '>', 1)->delete();
DB::table('user_wallets')->where('user_id', '>', 1)->delete();
DB::table('admin_users')->where('id', '>', 100)->delete();
echo "✅ Cleanup complete\n\n";

// ============================================================================
// TEST 1: Auto Wallet Creation
// ============================================================================
echo "📋 TEST SUITE 1: AUTO WALLET CREATION\n";
echo str_repeat("-", 80) . "\n";

test("Create new driver and verify wallet auto-creation", function() {
    $driver = new User();
    $driver->name = "Test Driver " . time();
    $driver->email = "driver_" . time() . "@test.com";
    $driver->username = "driver_" . time();
    $driver->password = bcrypt('password');
    $driver->phone_number = "+1234567890";
    $driver->user_type = 'driver';
    $driver->save();
    
    // Give it a moment for the event to fire
    sleep(1);
    
    // Check if wallet was created
    $wallet = UserWallet::where('user_id', $driver->id)->first();
    
    if (!$wallet) {
        return "Wallet not created automatically";
    }
    
    if ($wallet->wallet_balance != 0) {
        return "Initial balance should be 0, got: " . $wallet->wallet_balance;
    }
    
    if ($wallet->total_earnings != 0) {
        return "Initial earnings should be 0, got: " . $wallet->total_earnings;
    }
    
    echo "    📊 Driver ID: {$driver->id}\n";
    echo "    📊 Wallet ID: {$wallet->id}\n";
    echo "    📊 Initial Balance: " . formatMoney($wallet->wallet_balance) . "\n";
    
    // Store for later tests
    global $testDriver;
    $testDriver = $driver;
    
    return true;
});

test("Create new customer", function() {
    $customer = new User();
    $customer->name = "Test Customer " . time();
    $customer->email = "customer_" . time() . "@test.com";
    $customer->username = "customer_" . time();
    $customer->password = bcrypt('password');
    $customer->phone_number = "+1234567891";
    $customer->user_type = 'customer';
    $customer->save();
    
    echo "    📊 Customer ID: {$customer->id}\n";
    
    // Store for later tests
    global $testCustomer;
    $testCustomer = $customer;
    
    return true;
});

echo "\n";

// ============================================================================
// TEST 2: Payment Distribution (90/10 Split)
// ============================================================================
echo "📋 TEST SUITE 2: PAYMENT DISTRIBUTION (90/10 SPLIT)\n";
echo str_repeat("-", 80) . "\n";

$testAmount = 100.00; // $100 test payment

test("Create negotiation with agreed price of " . formatMoney($testAmount), function() use ($testAmount) {
    global $testDriver, $testCustomer, $testNegotiation;
    
    $negotiation = new Negotiation();
    $negotiation->customer_id = $testCustomer->id;
    $negotiation->customer_name = $testCustomer->name;
    $negotiation->driver_id = $testDriver->id;
    $negotiation->driver_name = $testDriver->name;
    $negotiation->status = 'Accepted';
    $negotiation->agreed_price = $testAmount;
    $negotiation->pickup_address = "123 Test St";
    $negotiation->dropoff_address = "456 Test Ave";
    $negotiation->pickup_lat = 43.6532;
    $negotiation->pickup_lng = -79.3832;
    $negotiation->dropoff_lat = 43.6532;
    $negotiation->dropoff_lng = -79.3832;
    $negotiation->save();
    
    echo "    📊 Negotiation ID: {$negotiation->id}\n";
    echo "    📊 Agreed Price: " . formatMoney($negotiation->agreed_price) . "\n";
    
    $testNegotiation = $negotiation;
    
    return true;
});

test("Mark payment as paid and verify distribution", function() use ($testAmount) {
    global $testNegotiation, $testDriver;
    
    // Get initial balances
    $driverWallet = UserWallet::where('user_id', $testDriver->id)->first();
    $companyWallet = Negotiation::getOrCreateCompanyWallet();
    
    $driverBalanceBefore = $driverWallet->wallet_balance;
    $companyBalanceBefore = $companyWallet->wallet_balance;
    
    echo "    📊 Driver balance before: " . formatMoney($driverBalanceBefore) . "\n";
    echo "    📊 Company balance before: " . formatMoney($companyBalanceBefore) . "\n";
    
    // Mark as paid (should trigger distribution)
    $result = $testNegotiation->markAsPaid('test_payment_id');
    
    if (!$result) {
        return "Failed to mark as paid";
    }
    
    // Refresh wallets
    $driverWallet->refresh();
    $companyWallet->refresh();
    
    $expectedDriverAmount = round($testAmount * 0.90, 2);
    $expectedCompanyAmount = round($testAmount * 0.10, 2);
    
    echo "    📊 Driver balance after: " . formatMoney($driverWallet->wallet_balance) . "\n";
    echo "    📊 Company balance after: " . formatMoney($companyWallet->wallet_balance) . "\n";
    echo "    📊 Expected driver amount: " . formatMoney($expectedDriverAmount) . "\n";
    echo "    📊 Expected company amount: " . formatMoney($expectedCompanyAmount) . "\n";
    
    // Verify driver balance (90%)
    $driverIncrease = $driverWallet->wallet_balance - $driverBalanceBefore;
    if (abs($driverIncrease - $expectedDriverAmount) > 0.01) {
        return "Driver balance incorrect. Expected increase: " . formatMoney($expectedDriverAmount) . ", Got: " . formatMoney($driverIncrease);
    }
    
    // Verify company balance (10%)
    $companyIncrease = $companyWallet->wallet_balance - $companyBalanceBefore;
    if (abs($companyIncrease - $expectedCompanyAmount) > 0.01) {
        return "Company balance incorrect. Expected increase: " . formatMoney($expectedCompanyAmount) . ", Got: " . formatMoney($companyIncrease);
    }
    
    return true;
});

test("Verify 2 transactions were created", function() {
    global $testNegotiation;
    
    $transactions = Transaction::where('negotiation_id', $testNegotiation->id)->get();
    
    if ($transactions->count() != 2) {
        return "Expected 2 transactions, found: " . $transactions->count();
    }
    
    // Verify driver transaction
    $driverTxn = $transactions->where('category', 'ride_earning')->first();
    if (!$driverTxn) {
        return "Driver transaction (ride_earning) not found";
    }
    
    // Verify company transaction
    $companyTxn = $transactions->where('category', 'service_fee')->first();
    if (!$companyTxn) {
        return "Company transaction (service_fee) not found";
    }
    
    echo "    📊 Driver Transaction:\n";
    echo "        - ID: {$driverTxn->id}\n";
    echo "        - Type: {$driverTxn->type}\n";
    echo "        - Category: {$driverTxn->category}\n";
    echo "        - Amount: " . formatMoney($driverTxn->amount) . "\n";
    echo "        - Reference: {$driverTxn->reference}\n";
    echo "        - Balance Before: " . formatMoney($driverTxn->balance_before) . "\n";
    echo "        - Balance After: " . formatMoney($driverTxn->balance_after) . "\n";
    
    echo "    📊 Company Transaction:\n";
    echo "        - ID: {$companyTxn->id}\n";
    echo "        - Type: {$companyTxn->type}\n";
    echo "        - Category: {$companyTxn->category}\n";
    echo "        - Amount: " . formatMoney($companyTxn->amount) . "\n";
    echo "        - Reference: {$companyTxn->reference}\n";
    echo "        - Balance Before: " . formatMoney($companyTxn->balance_before) . "\n";
    echo "        - Balance After: " . formatMoney($companyTxn->balance_after) . "\n";
    
    return true;
});

test("Verify transaction balances match wallet balances", function() {
    global $testNegotiation, $testDriver;
    
    $transactions = Transaction::where('negotiation_id', $testNegotiation->id)->get();
    
    // Check driver transaction
    $driverTxn = $transactions->where('category', 'ride_earning')->first();
    $driverWallet = UserWallet::where('user_id', $testDriver->id)->first();
    
    if ($driverTxn->balance_after != $driverWallet->wallet_balance) {
        return "Driver transaction balance_after doesn't match wallet balance";
    }
    
    // Check company transaction
    $companyTxn = $transactions->where('category', 'service_fee')->first();
    $companyWallet = Negotiation::getOrCreateCompanyWallet();
    
    if ($companyTxn->balance_after != $companyWallet->wallet_balance) {
        return "Company transaction balance_after doesn't match wallet balance";
    }
    
    echo "    📊 Balances are consistent ✓\n";
    
    return true;
});

echo "\n";

// ============================================================================
// TEST 3: Idempotency (No Duplicate Distributions)
// ============================================================================
echo "📋 TEST SUITE 3: IDEMPOTENCY (NO DUPLICATE DISTRIBUTIONS)\n";
echo str_repeat("-", 80) . "\n";

test("Calling markAsPaid again should not duplicate distribution", function() {
    global $testNegotiation, $testDriver;
    
    // Get current balances
    $driverWallet = UserWallet::where('user_id', $testDriver->id)->first();
    $companyWallet = Negotiation::getOrCreateCompanyWallet();
    
    $driverBalanceBefore = $driverWallet->wallet_balance;
    $companyBalanceBefore = $companyWallet->wallet_balance;
    $transactionCountBefore = Transaction::where('negotiation_id', $testNegotiation->id)->count();
    
    echo "    📊 Driver balance before 2nd call: " . formatMoney($driverBalanceBefore) . "\n";
    echo "    📊 Company balance before 2nd call: " . formatMoney($companyBalanceBefore) . "\n";
    echo "    📊 Transaction count before: {$transactionCountBefore}\n";
    
    // Call markAsPaid again
    $testNegotiation->markAsPaid('test_payment_id_duplicate');
    
    // Refresh wallets
    $driverWallet->refresh();
    $companyWallet->refresh();
    
    $transactionCountAfter = Transaction::where('negotiation_id', $testNegotiation->id)->count();
    
    echo "    📊 Driver balance after 2nd call: " . formatMoney($driverWallet->wallet_balance) . "\n";
    echo "    📊 Company balance after 2nd call: " . formatMoney($companyWallet->wallet_balance) . "\n";
    echo "    📊 Transaction count after: {$transactionCountAfter}\n";
    
    // Balances should not change
    if ($driverWallet->wallet_balance != $driverBalanceBefore) {
        return "Driver balance changed on duplicate call!";
    }
    
    if ($companyWallet->wallet_balance != $companyBalanceBefore) {
        return "Company balance changed on duplicate call!";
    }
    
    // Transaction count should remain the same
    if ($transactionCountAfter != $transactionCountBefore) {
        return "Transaction count changed from {$transactionCountBefore} to {$transactionCountAfter}!";
    }
    
    return true;
});

echo "\n";

// ============================================================================
// TEST 4: Different Payment Amounts
// ============================================================================
echo "📋 TEST SUITE 4: DIFFERENT PAYMENT AMOUNTS\n";
echo str_repeat("-", 80) . "\n";

$testAmounts = [50.00, 250.75, 1000.00];

foreach ($testAmounts as $amount) {
    test("Test payment distribution for " . formatMoney($amount), function() use ($amount) {
        global $testDriver, $testCustomer;
        
        // Create new negotiation
        $negotiation = new Negotiation();
        $negotiation->customer_id = $testCustomer->id;
        $negotiation->customer_name = $testCustomer->name;
        $negotiation->driver_id = $testDriver->id;
        $negotiation->driver_name = $testDriver->name;
        $negotiation->status = 'Accepted';
        $negotiation->agreed_price = $amount;
        $negotiation->pickup_address = "123 Test St";
        $negotiation->dropoff_address = "456 Test Ave";
        $negotiation->pickup_lat = 43.6532;
        $negotiation->pickup_lng = -79.3832;
        $negotiation->dropoff_lat = 43.6532;
        $negotiation->dropoff_lng = -79.3832;
        $negotiation->save();
        
        // Mark as paid
        $negotiation->markAsPaid('test_' . time());
        
        // Verify transactions
        $transactions = Transaction::where('negotiation_id', $negotiation->id)->get();
        
        if ($transactions->count() != 2) {
            return "Expected 2 transactions";
        }
        
        $driverTxn = $transactions->where('category', 'ride_earning')->first();
        $companyTxn = $transactions->where('category', 'service_fee')->first();
        
        $expectedDriver = round($amount * 0.90, 2);
        $expectedCompany = round($amount * 0.10, 2);
        
        echo "    📊 Total: " . formatMoney($amount) . "\n";
        echo "    📊 Driver (90%): " . formatMoney($driverTxn->amount) . " (expected: " . formatMoney($expectedDriver) . ")\n";
        echo "    📊 Company (10%): " . formatMoney($companyTxn->amount) . " (expected: " . formatMoney($expectedCompany) . ")\n";
        
        if (abs($driverTxn->amount - $expectedDriver) > 0.01) {
            return "Driver amount incorrect";
        }
        
        if (abs($companyTxn->amount - $expectedCompany) > 0.01) {
            return "Company amount incorrect";
        }
        
        return true;
    });
}

echo "\n";

// ============================================================================
// FINAL REPORT
// ============================================================================
echo "\n";
echo "=" . str_repeat("=", 78) . "=\n";
echo "  TEST RESULTS\n";
echo "=" . str_repeat("=", 78) . "=\n";
echo "\n";
echo "  Total Tests Run:    $testsRun\n";
echo "  ✅ Tests Passed:    $testsPassed\n";
echo "  ❌ Tests Failed:    $testsFailed\n";
echo "\n";

if ($testsFailed === 0) {
    echo "🎉 ALL TESTS PASSED! 🎉\n";
    echo "\n";
    echo "✅ Wallet auto-creation: WORKING\n";
    echo "✅ Payment distribution: WORKING\n";
    echo "✅ 90/10 split: ACCURATE\n";
    echo "✅ Transaction creation: WORKING\n";
    echo "✅ Balance integrity: VERIFIED\n";
    echo "✅ Idempotency: CONFIRMED\n";
} else {
    echo "⚠️  SOME TESTS FAILED\n";
    echo "Please review the errors above.\n";
}

echo "\n";
echo "=" . str_repeat("=", 78) . "=\n";
echo "\n";

// Display final wallet balances
echo "📊 FINAL WALLET BALANCES:\n";
echo str_repeat("-", 80) . "\n";

$driverWallet = UserWallet::where('user_id', $testDriver->id)->first();
$companyWallet = Negotiation::getOrCreateCompanyWallet();

echo "Driver Wallet (ID: {$testDriver->id}):\n";
echo "  Balance: " . formatMoney($driverWallet->wallet_balance) . "\n";
echo "  Total Earnings: " . formatMoney($driverWallet->total_earnings) . "\n";
echo "\n";

echo "Company Wallet (ID: 1):\n";
echo "  Balance: " . formatMoney($companyWallet->wallet_balance) . "\n";
echo "  Total Earnings: " . formatMoney($companyWallet->total_earnings) . "\n";
echo "\n";

$allTransactions = Transaction::where('negotiation_id', '>', 0)
    ->orderBy('created_at', 'desc')
    ->get();

echo "📊 RECENT TRANSACTIONS ({$allTransactions->count()}):\n";
echo str_repeat("-", 80) . "\n";

foreach ($allTransactions->take(10) as $txn) {
    echo "#{$txn->id} - {$txn->category} - " . formatMoney($txn->amount) . " - {$txn->reference}\n";
}

echo "\n";
