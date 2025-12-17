<?php

// Test Payout Requests API
require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Database connection
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "negoride";
$socket = "/Applications/MAMP/tmp/mysql/mysql.sock";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;unix_socket=$socket", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connected successfully\n\n";
} catch(PDOException $e) {
    die("❌ Connection failed: " . $e->getMessage() . "\n");
}

// Test configuration
$BASE_URL = "http://10.0.2.2:8888/negoride-canada-api/api";
$testUser = [
    'username' => '+256706638494',
    'password' => '1234'
];

echo "===========================================\n";
echo "🧪 PAYOUT REQUESTS API TEST\n";
echo "===========================================\n\n";

// Function to make API requests
function apiRequest($method, $endpoint, $data = null, $token = null) {
    global $BASE_URL;
    
    $ch = curl_init();
    $url = $BASE_URL . $endpoint;
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
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
        'data' => json_decode($response, true)
    ];
}

// Step 1: Login
echo "📝 Step 1: Login as driver\n";
$loginResponse = apiRequest('POST', '/users/login', $testUser);

echo "Login Response Code: " . $loginResponse['code'] . "\n";
echo "Login Response Data: " . json_encode($loginResponse['data']) . "\n";

if ($loginResponse['code'] !== 200 || !isset($loginResponse['data']['data'][0]['remember_token'])) {
    die("❌ Login failed\n");
}

$token = $loginResponse['data']['data'][0]['remember_token'];
$userId = $loginResponse['data']['data'][0]['id'];
echo "✅ Logged in successfully (User ID: $userId)\n";
echo "🔑 Token: " . substr($token, 0, 30) . "...\n\n";

// Step 2: Check payout account
echo "📝 Step 2: Check payout account\n";

// First, ensure user has a payout account in database
try {
    $stmt = $conn->prepare("SELECT id, status FROM payout_accounts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        echo "⚠️  No payout account found in DB. Creating one...\n";
        $stmt = $conn->prepare("INSERT INTO payout_accounts (user_id, account_type, status, is_active, created_at, updated_at) VALUES (?, 'express', 'active', 1, NOW(), NOW())");
        $stmt->execute([$userId]);
        echo "✅ Dummy payout account created in database\n";
    } else {
        echo "✅ Payout account exists in DB (ID: {$account['id']}, Status: {$account['status']})\n";
        
        // Update to active if needed
        if ($account['status'] !== 'active') {
            $stmt = $conn->prepare("UPDATE payout_accounts SET status = 'active', is_active = 1 WHERE id = ?");
            $stmt->execute([$account['id']]);
            echo "✅ Updated payout account to active status\n";
        }
    }
} catch (Exception $e) {
    echo "⚠️  Database error: " . $e->getMessage() . "\n";
}

$accountResponse = apiRequest('GET', '/payout-account', null, $token);
echo "API Response Code: " . $accountResponse['code'] . "\n";
if ($accountResponse['code'] === 200) {
    echo "✅ Payout account retrieved from API\n\n";
} else {
    echo "⚠️  API Response: " . json_encode($accountResponse['data']) . "\n\n";
}

// Step 3: Get statistics
echo "📝 Step 3: Get payout statistics\n";
$statsResponse = apiRequest('GET', '/payout-requests/statistics', null, $token);
echo "Response Code: " . $statsResponse['code'] . "\n";
echo "Data: " . json_encode($statsResponse['data'], JSON_PRETTY_PRINT) . "\n\n";

// Step 4: Create payout request
echo "📝 Step 4: Create payout request\n";
$payoutData = [
    'amount' => 50,
    'payout_method' => 'standard',
    'description' => 'Test payout request from API'
];
$createResponse = apiRequest('POST', '/payout-requests', $payoutData, $token);
echo "Response Code: " . $createResponse['code'] . "\n";
echo "Data: " . json_encode($createResponse['data'], JSON_PRETTY_PRINT) . "\n\n";

if ($createResponse['code'] === 201 && isset($createResponse['data']['data']['id'])) {
    $payoutRequestId = $createResponse['data']['data']['id'];
    echo "✅ Payout request created (ID: $payoutRequestId)\n\n";
    
    // Step 5: Get all payout requests
    echo "📝 Step 5: Get all payout requests\n";
    $listResponse = apiRequest('GET', '/payout-requests', null, $token);
    echo "Response Code: " . $listResponse['code'] . "\n";
    echo "Total Requests: " . count($listResponse['data']['data']) . "\n\n";
    
    // Step 6: Get single payout request
    echo "📝 Step 6: Get single payout request\n";
    $singleResponse = apiRequest('GET', "/payout-requests/$payoutRequestId", null, $token);
    echo "Response Code: " . $singleResponse['code'] . "\n";
    echo "Data: " . json_encode($singleResponse['data'], JSON_PRETTY_PRINT) . "\n\n";
    
    // Step 7: Cancel payout request
    echo "📝 Step 7: Cancel payout request\n";
    $cancelResponse = apiRequest('POST', "/payout-requests/$payoutRequestId/cancel", null, $token);
    echo "Response Code: " . $cancelResponse['code'] . "\n";
    echo "Data: " . json_encode($cancelResponse['data'], JSON_PRETTY_PRINT) . "\n\n";
    
    if ($cancelResponse['code'] === 200) {
        echo "✅ Payout request cancelled successfully\n\n";
    }
    
    // Step 8: Create instant payout request
    echo "📝 Step 8: Create instant payout request\n";
    $instantPayoutData = [
        'amount' => 100,
        'payout_method' => 'instant',
        'description' => 'Test instant payout'
    ];
    $instantResponse = apiRequest('POST', '/payout-requests', $instantPayoutData, $token);
    echo "Response Code: " . $instantResponse['code'] . "\n";
    echo "Data: " . json_encode($instantResponse['data'], JSON_PRETTY_PRINT) . "\n\n";
    
    if ($instantResponse['code'] === 201) {
        $instantId = $instantResponse['data']['data']['id'];
        echo "✅ Instant payout request created (ID: $instantId)\n";
        echo "   Amount: $" . $instantResponse['data']['data']['amount'] . "\n";
        echo "   Fee: $" . $instantResponse['data']['data']['fee_amount'] . "\n";
        echo "   Net Amount: $" . $instantResponse['data']['data']['net_amount'] . "\n\n";
    }
    
    // Step 9: Get updated statistics
    echo "📝 Step 9: Get updated statistics\n";
    $finalStatsResponse = apiRequest('GET', '/payout-requests/statistics', null, $token);
    echo "Data: " . json_encode($finalStatsResponse['data'], JSON_PRETTY_PRINT) . "\n\n";
    
} else {
    echo "❌ Failed to create payout request\n";
    echo "Error: " . ($createResponse['data']['message'] ?? 'Unknown error') . "\n\n";
}

echo "===========================================\n";
echo "✅ PAYOUT REQUESTS API TEST COMPLETED\n";
echo "===========================================\n";
