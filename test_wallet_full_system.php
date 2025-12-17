<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\UserWallet;
use App\Models\Transaction;
use App\Models\Negotiation;
use Illuminate\Support\Facades\DB;

echo "\n🚀 WALLET SYSTEM FULL TEST - John Doe\n";
echo str_repeat("=", 80) . "\n\n";

// Find John Doe
$john = User::where('name', 'like', '%John Doe%')->first();

if (!$john) {
    echo "❌ John Doe not found. Searching for any driver...\n";
    $john = User::where('user_type', 'driver')->first();
}

if (!$john) {
    echo "❌ No driver found in system!\n";
    exit(1);
}

echo "✅ Found Driver: {$john->name} (ID: {$john->id})\n";
echo "   Email: {$john->email}\n";
echo "   Phone: {$john->phone_number}\n\n";

// Get or create wallet
echo "📱 Getting/Creating Wallet...\n";
$wallet = $john->getOrCreateWallet();
echo "✅ Wallet ID: {$wallet->id}\n";
echo "   Initial Balance: \${$wallet->wallet_balance}\n";
echo "   Total Earnings: \${$wallet->total_earnings}\n\n";

// Clear existing test transactions
echo "🧹 Cleaning up old test transactions...\n";
$deletedCount = Transaction::where('user_id', $john->id)->delete();
echo "   Deleted {$deletedCount} old transactions\n\n";

// Reset wallet balances
$wallet->wallet_balance = 0;
$wallet->total_earnings = 0;
$wallet->save();
echo "✅ Wallet reset to \$0.00\n\n";

// Find or create a test negotiation
echo "🚗 Setting up test trip/negotiation...\n";
$negotiation = Negotiation::where('driver_id', $john->id)->first();

if (!$negotiation) {
    // Create a dummy negotiation
    $customer = User::where('user_type', 'customer')->first();
    if (!$customer) {
        $customer = User::where('id', '!=', $john->id)->first();
    }
    
    $negotiation = new Negotiation();
    $negotiation->customer_id = $customer->id ?? 1;
    $negotiation->customer_name = $customer->name ?? 'Test Customer';
    $negotiation->driver_id = $john->id;
    $negotiation->driver_name = $john->name;
    $negotiation->status = 'Completed';
    $negotiation->pickup_address = '123 Test Street, Toronto, ON';
    $negotiation->dropoff_address = '456 Demo Avenue, Toronto, ON';
    $negotiation->pickup_lat = 43.6532;
    $negotiation->pickup_lng = -79.3832;
    $negotiation->dropoff_lat = 43.7184;
    $negotiation->dropoff_lng = -79.5181;
    $negotiation->save();
    echo "   Created test negotiation ID: {$negotiation->id}\n";
} else {
    echo "   Using existing negotiation ID: {$negotiation->id}\n";
}
echo "\n";

// Create dummy transactions
echo "💰 Creating Dummy Transactions...\n";
echo str_repeat("-", 80) . "\n\n";

$transactionData = [
    [
        'type' => 'credit',
        'category' => 'ride_earning',
        'amount' => 45.00,
        'description' => 'Ride from Downtown to Airport',
        'reference' => 'RIDE-' . strtoupper(substr(md5(time() . '1'), 0, 8)),
    ],
    [
        'type' => 'debit',
        'category' => 'service_fee',
        'amount' => 4.50,
        'description' => 'Platform service fee (10%)',
        'reference' => 'FEE-' . strtoupper(substr(md5(time() . '2'), 0, 8)),
    ],
    [
        'type' => 'credit',
        'category' => 'ride_earning',
        'amount' => 32.00,
        'description' => 'Ride from Scarborough to Mississauga',
        'reference' => 'RIDE-' . strtoupper(substr(md5(time() . '3'), 0, 8)),
    ],
    [
        'type' => 'debit',
        'category' => 'service_fee',
        'amount' => 3.20,
        'description' => 'Platform service fee (10%)',
        'reference' => 'FEE-' . strtoupper(substr(md5(time() . '4'), 0, 8)),
    ],
    [
        'type' => 'credit',
        'category' => 'bonus',
        'amount' => 20.00,
        'description' => 'Weekly performance bonus',
        'reference' => 'BONUS-' . strtoupper(substr(md5(time() . '5'), 0, 8)),
    ],
    [
        'type' => 'credit',
        'category' => 'ride_earning',
        'amount' => 58.50,
        'description' => 'Ride from York to Vaughan',
        'reference' => 'RIDE-' . strtoupper(substr(md5(time() . '6'), 0, 8)),
    ],
    [
        'type' => 'debit',
        'category' => 'service_fee',
        'amount' => 5.85,
        'description' => 'Platform service fee (10%)',
        'reference' => 'FEE-' . strtoupper(substr(md5(time() . '7'), 0, 8)),
    ],
    [
        'type' => 'credit',
        'category' => 'refund',
        'amount' => 15.00,
        'description' => 'Refund for cancelled trip',
        'reference' => 'REFUND-' . strtoupper(substr(md5(time() . '8'), 0, 8)),
    ],
];

$currentBalance = 0;
$totalEarnings = 0;

foreach ($transactionData as $index => $data) {
    $balanceBefore = $currentBalance;
    
    if ($data['type'] === 'credit') {
        $currentBalance += $data['amount'];
        $totalEarnings += $data['amount'];
    } else {
        $currentBalance -= $data['amount'];
    }
    
    $transaction = new Transaction();
    $transaction->user_id = $john->id;
    $transaction->type = $data['type'];
    $transaction->category = $data['category'];
    $transaction->amount = $data['amount'];
    $transaction->balance_before = $balanceBefore;
    $transaction->balance_after = $currentBalance;
    $transaction->reference = $data['reference'];
    $transaction->description = $data['description'];
    $transaction->status = 'completed';
    $transaction->negotiation_id = $negotiation->id;
    $transaction->metadata = json_encode([
        'driver' => $john->name,
        'trip_id' => $negotiation->id,
        'timestamp' => now()->toDateTimeString(),
    ]);
    $transaction->save();
    
    $icon = $data['type'] === 'credit' ? '💚' : '❤️';
    $sign = $data['type'] === 'credit' ? '+' : '-';
    
    echo "{$icon} Transaction #{$transaction->id} - {$data['category']}\n";
    echo "   Amount: {$sign}\${$data['amount']}\n";
    echo "   Description: {$data['description']}\n";
    echo "   Reference: {$data['reference']}\n";
    echo "   Balance: \${$balanceBefore} → \${$currentBalance}\n";
    echo "\n";
    
    // Simulate real-time delays
    usleep(100000); // 0.1 second delay
}

// Update wallet with final balances
$wallet->wallet_balance = $currentBalance;
$wallet->total_earnings = $totalEarnings;
$wallet->save();

echo str_repeat("=", 80) . "\n";
echo "✅ ALL TRANSACTIONS CREATED SUCCESSFULLY!\n\n";

// Display summary
echo "📊 WALLET SUMMARY\n";
echo str_repeat("-", 80) . "\n";
echo "Driver: {$john->name}\n";
echo "Wallet ID: {$wallet->id}\n";
echo "Current Balance: \${$wallet->wallet_balance}\n";
echo "Total Lifetime Earnings: \${$wallet->total_earnings}\n";
echo "Total Transactions: " . Transaction::where('user_id', $john->id)->count() . "\n";
echo "\n";

// Transaction breakdown
$credits = Transaction::where('user_id', $john->id)->where('type', 'credit')->sum('amount');
$debits = Transaction::where('user_id', $john->id)->where('type', 'debit')->sum('amount');

echo "💰 TRANSACTION BREAKDOWN\n";
echo str_repeat("-", 80) . "\n";
echo "Total Credits (Money In): \${$credits}\n";
echo "Total Debits (Money Out): \${$debits}\n";
echo "Net Amount: \$" . ($credits - $debits) . "\n";
echo "\n";

// Category breakdown
echo "📈 BREAKDOWN BY CATEGORY\n";
echo str_repeat("-", 80) . "\n";

$categories = Transaction::where('user_id', $john->id)
    ->selectRaw('category, type, SUM(amount) as total, COUNT(*) as count')
    ->groupBy('category', 'type')
    ->get();

foreach ($categories as $cat) {
    $icon = $cat->type === 'credit' ? '💚' : '❤️';
    echo "{$icon} {$cat->category}: \${$cat->total} ({$cat->count} transaction(s))\n";
}

echo "\n";
echo str_repeat("=", 80) . "\n";
echo "🎉 TEST COMPLETE! You can now check the mobile app wallet screen.\n";
echo "\n";
echo "📱 API ENDPOINTS TO TEST:\n";
echo "   GET: /api/wallet (Get wallet info)\n";
echo "   GET: /api/wallet/transactions (Get all transactions)\n";
echo "   GET: /api/wallet/summary (Get wallet summary with stats)\n";
echo "   GET: /api/wallet/earnings?period=all (Get earnings statistics)\n";
echo "\n";
echo "🔑 Use JWT token for user ID: {$john->id} (John Doe)\n";
echo str_repeat("=", 80) . "\n\n";
