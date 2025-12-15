<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Negotiation;

echo "Testing Stripe Payment Link Creation\n";
echo "=====================================\n\n";

$neg = Negotiation::find(43);

if (!$neg) {
    echo "❌ Negotiation #43 not found\n";
    exit(1);
}

echo "✅ Negotiation #43 found\n";
echo "   Customer ID: " . $neg->customer_id . "\n";
echo "   Driver ID: " . $neg->driver_id . "\n";
echo "   Agreed Price: $" . number_format($neg->agreed_price / 100, 2) . " CAD\n";
echo "   Status: " . $neg->status . "\n";
echo "   Payment Status: " . ($neg->payment_status ?? 'none') . "\n\n";

if ($neg->stripe_url) {
    echo "ℹ️  Payment link already exists:\n";
    echo "   " . $neg->stripe_url . "\n\n";
    echo "   Forcing regeneration...\n\n";
    $neg->stripe_id = null;
    $neg->stripe_url = null;
    $neg->stripe_product_id = null;
    $neg->stripe_price_id = null;
    $neg->save();
}

echo "🔄 Creating Stripe payment link...\n\n";

try {
    $neg->create_payment_link();
    
    echo "✅ SUCCESS! Payment link created:\n\n";
    echo "   Stripe URL: " . $neg->stripe_url . "\n";
    echo "   Stripe ID: " . $neg->stripe_id . "\n";
    echo "   Product ID: " . $neg->stripe_product_id . "\n";
    echo "   Price ID: " . $neg->stripe_price_id . "\n";
    echo "   Amount: $" . number_format($neg->agreed_price / 100, 2) . " CAD\n\n";
    
    echo "🎉 You can test payment at: " . $neg->stripe_url . "\n\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "✅ Test completed successfully!\n";
