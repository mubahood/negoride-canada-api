<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\UserWallet;
use App\Models\Negotiation;
use Illuminate\Support\Facades\DB;

echo "\n========================================\n";
echo "WALLET SYSTEM COMPREHENSIVE TEST\n";
echo "========================================\n\n";

// Get John Doe
$johnDoe = User::find(2);
if (!$johnDoe) {
    die("❌ John Doe (ID: 2) not found!\n");
}

echo "✅ Found driver: {$johnDoe->name} (ID: {$johnDoe->id})\n";
echo "   Phone: {$johnDoe->phone_number}\n";
echo "   Type: {$johnDoe->user_type}\n\n";

// Get or create wallet
$wallet = $johnDoe->getOrCreateWallet();
echo "💰 Initial Wallet State:\n";
echo "   Balance: \${$wallet->wallet_balance}\n";
echo "   Total Earnings: \${$wallet->total_earnings}\n\n";

// Clear existing test data for John Doe
echo "🧹 Cleaning up old test data...\n";
Transaction::where('user_id', $johnDoe->id)->delete();
Payment::where('driver_id', $johnDoe->id)->delete();
Negotiation::where('driver_id', $johnDoe->id)->delete();
$wallet->update(['wallet_balance' => 0, 'total_earnings' => 0]);
echo "✅ Cleanup complete\n\n";

// Create test customer
$customer = User::where('user_type', 'Customer')->first();
if (!$customer) {
    echo "⚠️  No customer found, creating test customer...\n";
    $customer = User::create([
        'username' => '+1234567890',
        'password' => bcrypt('password'),
        'name' => 'Test Customer',
        'first_name' => 'Test',
        'last_name' => 'Customer',
        'sex' => 'Male',
        'phone_number' => '+1234567890',
        'user_type' => 'Customer',
        'status' => 1,
    ]);
}
echo "✅ Customer: {$customer->name} (ID: {$customer->id})\n\n";

// Test Scenario 1: Create completed trip with payment
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 1: Complete Trip with $100 Payment\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$negotiation1 = Negotiation::create([
    'customer_id' => $customer->id,
    'customer_name' => $customer->name,
    'driver_id' => $johnDoe->id,
    'driver_name' => $johnDoe->name,
    'pickup_lat' => '43.6532',
    'pickup_lng' => '-79.3832',
    'pickup_address' => 'Toronto, ON',
    'dropoff_lat' => '43.7615',
    'dropoff_lng' => '-79.4111',
    'dropoff_address' => 'North York, ON',
    'status' => 'Completed',
    'customer_accepted' => 'Accepted',
]);

echo "📍 Created negotiation #{$negotiation1->id}\n";

// Create payment with $100 (10000 cents)
$payment1 = Payment::create([
    'user_id' => $customer->id,
    'driver_id' => $johnDoe->id,
    'negotiation_id' => $negotiation1->id,
    'amount' => 10000, // $100 in cents
    'payment_status' => 'completed',
    'stripe_payment_intent_id' => 'pi_test_' . uniqid(),
    'stripe_charge_id' => 'ch_test_' . uniqid(),
    'payment_method' => 'card',
]);

echo "💳 Created payment #{$payment1->id}: \$100.00\n";
echo "   Status: {$payment1->payment_status}\n\n";

echo "⏳ Triggering wallet distribution...\n";
// The payment observer should automatically trigger wallet distribution
// Let's verify it worked

sleep(1); // Give time for observers to fire

$wallet->refresh();
$transactions = Transaction::where('user_id', $johnDoe->id)->get();

echo "\n📊 Results after Payment 1:\n";
echo "   Wallet Balance: \${$wallet->wallet_balance}\n";
echo "   Total Earnings: \${$wallet->total_earnings}\n";
echo "   Transaction Count: {$transactions->count()}\n\n";

if ($transactions->count() > 0) {
    echo "   Transactions:\n";
    foreach ($transactions as $t) {
        echo "   - {$t->category}: \${$t->amount} ({$t->type})\n";
        echo "     Balance: \${$t->balance_before} → \${$t->balance_after}\n";
    }
} else {
    echo "   ⚠️  No transactions found! Trigger may not have fired.\n";
}

echo "\n";

// Test Scenario 2: Another trip with $50
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 2: Second Trip with $50 Payment\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$negotiation2 = Negotiation::create([
    'customer_id' => $customer->id,
    'customer_name' => $customer->name,
    'driver_id' => $johnDoe->id,
    'driver_name' => $johnDoe->name,
    'pickup_lat' => '43.7615',
    'pickup_lng' => '-79.4111',
    'pickup_address' => 'North York, ON',
    'dropoff_lat' => '43.6532',
    'dropoff_lng' => '-79.3832',
    'dropoff_address' => 'Toronto, ON',
    'status' => 'Completed',
    'customer_accepted' => 'Accepted',
]);

$payment2 = Payment::create([
    'user_id' => $customer->id,
    'driver_id' => $johnDoe->id,
    'negotiation_id' => $negotiation2->id,
    'amount' => 5000, // $50 in cents
    'payment_status' => 'completed',
    'stripe_payment_intent_id' => 'pi_test_' . uniqid(),
    'stripe_charge_id' => 'ch_test_' . uniqid(),
    'payment_method' => 'card',
]);

echo "💳 Created payment #{$payment2->id}: \$50.00\n\n";

sleep(1);

$wallet->refresh();
$allTransactions = Transaction::where('user_id', $johnDoe->id)->orderBy('created_at')->get();

echo "📊 Results after Payment 2:\n";
echo "   Wallet Balance: \${$wallet->wallet_balance}\n";
echo "   Total Earnings: \${$wallet->total_earnings}\n";
echo "   Transaction Count: {$allTransactions->count()}\n\n";

echo "   All Transactions:\n";
foreach ($allTransactions as $t) {
    echo "   [{$t->id}] {$t->category}: \${$t->amount} ({$t->type})\n";
    echo "       Balance: \${$t->balance_before} → \${$t->balance_after}\n";
    echo "       Ref: {$t->reference}\n";
}

// Test Scenario 3: Verify calculations
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "VERIFICATION: Check 90/10 Split\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$totalPayments = Payment::where('driver_id', $johnDoe->id)
    ->where('payment_status', 'completed')
    ->sum('amount');

$totalPaymentsDollars = $totalPayments / 100;
$expectedDriverShare = ($totalPayments * 0.90) / 100;
$expectedServiceFee = ($totalPayments * 0.10) / 100;

echo "💰 Total Payments: \${$totalPaymentsDollars}\n";
echo "   Expected Driver Share (90%): \${$expectedDriverShare}\n";
echo "   Expected Service Fee (10%): \${$expectedServiceFee}\n\n";

$actualEarnings = Transaction::where('user_id', $johnDoe->id)
    ->where('category', 'ride_earning')
    ->sum('amount');

$actualServiceFees = Transaction::where('user_id', $johnDoe->id)
    ->where('category', 'service_fee')
    ->sum('amount');

echo "✅ Actual Driver Earnings: \${$actualEarnings}\n";
echo "✅ Actual Service Fees: \${$actualServiceFees}\n";
echo "✅ Wallet Balance: \${$wallet->wallet_balance}\n";
echo "✅ Total Earnings: \${$wallet->total_earnings}\n\n";

// Verification
$match = (
    abs($actualEarnings - $expectedDriverShare) < 0.01 &&
    abs($actualServiceFees - $expectedServiceFee) < 0.01 &&
    abs($wallet->wallet_balance - $expectedDriverShare) < 0.01
);

if ($match) {
    echo "🎉 SUCCESS! All calculations match!\n";
} else {
    echo "⚠️  WARNING: Calculations don't match!\n";
    echo "   Difference in earnings: $" . ($actualEarnings - $expectedDriverShare) . "\n";
    echo "   Difference in fees: $" . ($actualServiceFees - $expectedServiceFee) . "\n";
    echo "   Difference in balance: $" . ($wallet->wallet_balance - $expectedDriverShare) . "\n";
}

// Test API endpoint
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 4: API Endpoint Verification\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Simulate API call using WalletController
$controller = new \App\Http\Controllers\Api\WalletController();

// Mock authentication
auth()->login($johnDoe);

$walletResponse = $controller->getWallet();
$transactionsResponse = $controller->getTransactions(new \Illuminate\Http\Request());

echo "🌐 API Response - Wallet:\n";
$walletData = $walletResponse->getData(true);
echo "   Code: {$walletData['code']}\n";
echo "   Message: {$walletData['message']}\n";
if (isset($walletData['data'])) {
    echo "   Balance: \${$walletData['data']['wallet_balance']}\n";
    echo "   Earnings: \${$walletData['data']['total_earnings']}\n";
}

echo "\n🌐 API Response - Transactions:\n";
$transData = $transactionsResponse->getData(true);
echo "   Code: {$transData['code']}\n";
echo "   Message: {$transData['message']}\n";
echo "   Transaction Count: " . count($transData['data']) . "\n";

// Final Summary
echo "\n========================================\n";
echo "FINAL SUMMARY\n";
echo "========================================\n\n";

echo "Driver: {$johnDoe->name}\n";
echo "Total Completed Trips: " . Payment::where('driver_id', $johnDoe->id)->where('payment_status', 'completed')->count() . "\n";
echo "Total Revenue: \${$totalPaymentsDollars}\n";
echo "Driver Earnings (90%): \${$actualEarnings}\n";
echo "Platform Fees (10%): \${$actualServiceFees}\n";
echo "Current Wallet Balance: \${$wallet->wallet_balance}\n";
echo "Total Lifetime Earnings: \${$wallet->total_earnings}\n";
echo "Transaction Records: {$allTransactions->count()}\n\n";

if ($match && $transData['code'] == 1 && count($transData['data']) > 0) {
    echo "✅ ALL TESTS PASSED!\n";
    echo "✅ Wallet system is working correctly!\n";
    echo "✅ API endpoints are functional!\n";
    echo "✅ 90/10 split is accurate!\n";
    echo "✅ Ready for production!\n";
} else {
    echo "⚠️  Some tests need attention:\n";
    if (!$match) echo "   - Calculation mismatch\n";
    if ($transData['code'] != 1) echo "   - API error\n";
    if (count($transData['data']) == 0) echo "   - No transactions returned from API\n";
}

echo "\n========================================\n";
echo "Test completed at " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";
