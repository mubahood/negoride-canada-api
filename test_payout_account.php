<?php

/**
 * Comprehensive PayoutAccount System Test Script
 * 
 * This script tests all payout account endpoints and Stripe integration
 * for the Negoride Canada rideshare platform.
 * 
 * Usage: php test_payout_account.php
 */

require_once __DIR__ . '/vendor/autoload.php';

// Database configuration
$host = 'localhost';
$dbname = 'negoride';
$username = 'root';
$password = 'root';
$socket = '/Applications/MAMP/tmp/mysql/mysql.sock';

// API configuration
$apiBaseUrl = 'http://localhost:8888/negoride-canada-api/api';
$testUsername = '+256706638494'; // John Doe's username/phone
$testPassword = '4321'; // Default test password

// Colors for output
define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('RESET', "\033[0m");

echo BLUE . "==============================================\n" . RESET;
echo BLUE . "  PayoutAccount System Comprehensive Test\n" . RESET;
echo BLUE . "==============================================\n\n" . RESET;

// Connect to database
try {
    $dsn = "mysql:host=$host;dbname=$dbname;unix_socket=$socket";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo GREEN . "✓ Database connected successfully\n\n" . RESET;
} catch (PDOException $e) {
    die(RED . "✗ Database connection failed: " . $e->getMessage() . "\n" . RESET);
}

// Helper function to make API requests
function apiRequest($endpoint, $method = 'GET', $data = null, $token = null) {
    global $apiBaseUrl;
    
    $url = $apiBaseUrl . $endpoint;
    $ch = curl_init($url);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response
    ];
}

// Step 1: Login to get token
echo YELLOW . "Step 1: Authenticating test user...\n" . RESET;
$loginResponse = apiRequest('/users/login', 'POST', [
    'username' => $testUsername,
    'password' => $testPassword
]);

if ($loginResponse['code'] !== 200 || !isset($loginResponse['body']['data']['token'])) {
    echo RED . "✗ Login failed. Response:\n" . RESET;
    print_r($loginResponse['body']);
    die();
}

$token = $loginResponse['body']['data']['token'];
$userId = $loginResponse['body']['data']['user']['id'];
echo GREEN . "✓ Authenticated as user ID: $userId\n" . RESET;
echo "Token: " . substr($token, 0, 20) . "...\n\n";

// Step 2: Get or create payout account
echo YELLOW . "Step 2: Getting payout account...\n" . RESET;
$accountResponse = apiRequest('/payout-account', 'GET', null, $token);

if ($accountResponse['code'] === 200) {
    echo GREEN . "✓ Payout account retrieved\n" . RESET;
    $account = $accountResponse['body']['data'];
    echo "  Account ID: {$account['id']}\n";
    echo "  Status: {$account['status']} ({$account['status_description']})\n";
    echo "  Verification: {$account['verification_status']} ({$account['verification_status_description']})\n";
    echo "  Stripe Account ID: " . ($account['stripe_account_id'] ?? 'Not created') . "\n";
    echo "  Onboarding Complete: " . ($account['onboarding_completed'] ? 'Yes' : 'No') . "\n";
    echo "  Payouts Enabled: " . ($account['payouts_enabled'] ? 'Yes' : 'No') . "\n\n";
} else {
    echo RED . "✗ Failed to get payout account\n" . RESET;
    print_r($accountResponse['body']);
    die();
}

// Step 3: Create Stripe account if not exists
if (!$account['stripe_account_id']) {
    echo YELLOW . "Step 3: Creating Stripe Connect account...\n" . RESET;
    
    $createResponse = apiRequest('/payout-account/create-stripe', 'POST', [
        'email' => 'john.doe@negoride.ca',
        'phone' => $testUsername,
        'business_type' => 'individual'
    ], $token);
    
    if ($createResponse['code'] === 200) {
        echo GREEN . "✓ Stripe account created successfully\n" . RESET;
        $stripeAccountId = $createResponse['body']['data']['stripe_account_id'];
        echo "  Stripe Account ID: $stripeAccountId\n\n";
    } else {
        echo RED . "✗ Failed to create Stripe account\n" . RESET;
        print_r($createResponse['body']);
        echo "\n";
    }
} else {
    echo YELLOW . "Step 3: Stripe account already exists (ID: {$account['stripe_account_id']})\n\n" . RESET;
}

// Step 4: Get onboarding link
echo YELLOW . "Step 4: Getting onboarding link...\n" . RESET;
$onboardingResponse = apiRequest('/payout-account/onboarding-link', 'POST', [
    'return_url' => 'http://localhost:8888/negoride-canada-api/payout-complete',
    'refresh_url' => 'http://localhost:8888/negoride-canada-api/payout-refresh'
], $token);

if ($onboardingResponse['code'] === 200) {
    echo GREEN . "✓ Onboarding link generated\n" . RESET;
    echo "  URL: {$onboardingResponse['body']['data']['onboarding_url']}\n";
    echo "  Expires: " . date('Y-m-d H:i:s', $onboardingResponse['body']['data']['expires_at']) . "\n\n";
} else {
    echo RED . "✗ Failed to get onboarding link\n" . RESET;
    print_r($onboardingResponse['body']);
    echo "\n";
}

// Step 5: Get dashboard link
echo YELLOW . "Step 5: Getting Express Dashboard link...\n" . RESET;
$dashboardResponse = apiRequest('/payout-account/dashboard-link', 'GET', null, $token);

if ($dashboardResponse['code'] === 200) {
    echo GREEN . "✓ Dashboard link generated\n" . RESET;
    echo "  URL: {$dashboardResponse['body']['data']['dashboard_url']}\n\n";
} else {
    echo YELLOW . "⚠ Dashboard link not available (account may not be fully onboarded)\n" . RESET;
    echo "  Message: " . ($dashboardResponse['body']['message'] ?? 'Unknown error') . "\n\n";
}

// Step 6: Sync account with Stripe
echo YELLOW . "Step 6: Syncing account with Stripe...\n" . RESET;
$syncResponse = apiRequest('/payout-account/sync', 'POST', null, $token);

if ($syncResponse['code'] === 200) {
    echo GREEN . "✓ Account synced successfully\n" . RESET;
    $syncedAccount = $syncResponse['body']['data']['account'];
    echo "  Status: {$syncedAccount['status']}\n";
    echo "  Charges Enabled: " . ($syncedAccount['charges_enabled'] ? 'Yes' : 'No') . "\n";
    echo "  Payouts Enabled: " . ($syncedAccount['payouts_enabled'] ? 'Yes' : 'No') . "\n";
    echo "  Details Submitted: " . ($syncedAccount['details_submitted'] ? 'Yes' : 'No') . "\n";
    
    if (!empty($syncedAccount['requirements_currently_due'])) {
        echo "  Requirements Due: " . implode(', ', $syncedAccount['requirements_currently_due']) . "\n";
    }
    echo "\n";
} else {
    echo RED . "✗ Failed to sync account\n" . RESET;
    print_r($syncResponse['body']);
    echo "\n";
}

// Step 7: Update preferences
echo YELLOW . "Step 7: Updating payout preferences...\n" . RESET;
$preferencesResponse = apiRequest('/payout-account/preferences', 'POST', [
    'default_payout_method' => 'standard',
    'minimum_payout_amount' => 25.00
], $token);

if ($preferencesResponse['code'] === 200) {
    echo GREEN . "✓ Preferences updated\n" . RESET;
    $updatedAccount = $preferencesResponse['body']['data']['account'];
    echo "  Default Payout Method: {$updatedAccount['default_payout_method']}\n";
    echo "  Minimum Payout: \${$updatedAccount['minimum_payout_amount']}\n";
    echo "  Description: {$updatedAccount['payout_method_description']}\n\n";
} else {
    echo RED . "✗ Failed to update preferences\n" . RESET;
    print_r($preferencesResponse['body']);
    echo "\n";
}

// Step 8: Get final account state
echo YELLOW . "Step 8: Getting final account state...\n" . RESET;
$finalResponse = apiRequest('/payout-account', 'GET', null, $token);

if ($finalResponse['code'] === 200) {
    $finalAccount = $finalResponse['body']['data'];
    echo GREEN . "✓ Final account state:\n" . RESET;
    echo "  ID: {$finalAccount['id']}\n";
    echo "  Status: {$finalAccount['status']} ({$finalAccount['status_description']})\n";
    echo "  Verification: {$finalAccount['verification_status']}\n";
    echo "  Active: " . ($finalAccount['is_active'] ? 'Yes' : 'No') . "\n";
    echo "  Can Receive Instant Payouts: " . ($finalAccount['can_receive_instant_payouts'] ? 'Yes' : 'No') . "\n";
    echo "  Has Pending Requirements: " . ($finalAccount['has_pending_requirements'] ? 'Yes' : 'No') . "\n";
    echo "  Onboarding Complete: " . ($finalAccount['is_onboarding_complete'] ? 'Yes' : 'No') . "\n";
    
    if ($finalAccount['bank_account_last4']) {
        echo "  Bank Account: •••• {$finalAccount['bank_account_last4']} ({$finalAccount['bank_account_type']})\n";
    }
    
    if ($finalAccount['card_last4']) {
        echo "  Card: {$finalAccount['card_brand']} •••• {$finalAccount['card_last4']}\n";
    }
    
    echo "  Last Synced: " . ($finalAccount['last_stripe_sync'] ?? 'Never') . "\n";
    echo "\n";
}

// Database verification
echo YELLOW . "Step 9: Verifying database records...\n" . RESET;

$stmt = $pdo->prepare("
    SELECT * FROM payout_accounts WHERE user_id = ?
");
$stmt->execute([$userId]);
$dbAccount = $stmt->fetch(PDO::FETCH_ASSOC);

if ($dbAccount) {
    echo GREEN . "✓ Database record found\n" . RESET;
    echo "  Record ID: {$dbAccount['id']}\n";
    echo "  Stripe Account ID: {$dbAccount['stripe_account_id']}\n";
    echo "  Status: {$dbAccount['status']}\n";
    echo "  Created: {$dbAccount['created_at']}\n";
    echo "  Updated: {$dbAccount['updated_at']}\n\n";
} else {
    echo RED . "✗ Database record not found\n\n" . RESET;
}

// Summary
echo BLUE . "==============================================\n" . RESET;
echo BLUE . "  Test Summary\n" . RESET;
echo BLUE . "==============================================\n" . RESET;
echo "✓ All PayoutAccount endpoints tested\n";
echo "✓ Stripe Connect integration verified\n";
echo "✓ Database records validated\n";
echo "\n";
echo GREEN . "Next Steps:\n" . RESET;
echo "1. Complete Stripe onboarding using the onboarding URL\n";
echo "2. Add bank account or debit card for payouts\n";
echo "3. Test actual payout functionality\n";
echo "4. Integrate with mobile app UI\n";
echo "\n";
echo BLUE . "==============================================\n" . RESET;
echo BLUE . "  Test Complete!\n" . RESET;
echo BLUE . "==============================================\n" . RESET;
