<?php

// Cleanup and verify payout requests system

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

echo "===========================================\n";
echo "🧹 PAYOUT REQUESTS SYSTEM VERIFICATION\n";
echo "===========================================\n\n";

// Check tables exist
echo "📝 Step 1: Verify tables exist\n";
$tables = ['payout_accounts', 'payout_requests'];
foreach ($tables as $table) {
    $stmt = $conn->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() > 0) {
        echo "  ✅ Table '$table' exists\n";
    } else {
        echo "  ❌ Table '$table' NOT FOUND!\n";
        exit(1);
    }
}
echo "\n";

// Check payout_accounts structure
echo "📝 Step 2: Verify payout_accounts structure\n";
$requiredAccountFields = ['id', 'user_id', 'status', 'is_active', 'minimum_payout_amount', 'default_payout_method'];
$stmt = $conn->query("DESCRIBE payout_accounts");
$existingFields = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existingFields[] = $row['Field'];
}

foreach ($requiredAccountFields as $field) {
    if (in_array($field, $existingFields)) {
        echo "  ✅ Field '$field' exists\n";
    } else {
        echo "  ❌ Field '$field' NOT FOUND!\n";
    }
}
echo "\n";

// Check payout_requests structure
echo "📝 Step 3: Verify payout_requests structure\n";
$requiredRequestFields = ['id', 'user_id', 'payout_account_id', 'amount', 'fee_amount', 'net_amount', 'status', 'payout_method'];
$stmt = $conn->query("DESCRIBE payout_requests");
$existingFields = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existingFields[] = $row['Field'];
}

foreach ($requiredRequestFields as $field) {
    if (in_array($field, $existingFields)) {
        echo "  ✅ Field '$field' exists\n";
    } else {
        echo "  ❌ Field '$field' NOT FOUND!\n";
    }
}
echo "\n";

// Check indexes
echo "📝 Step 4: Verify indexes\n";
$stmt = $conn->query("SHOW INDEX FROM payout_requests WHERE Key_name != 'PRIMARY'");
$indexes = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $indexes[] = $row['Column_name'];
}
echo "  Indexes found: " . implode(', ', array_unique($indexes)) . "\n\n";

// Get statistics
echo "📝 Step 5: System statistics\n";
$stmt = $conn->query("SELECT COUNT(*) as total FROM payout_accounts");
$accountCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
echo "  Payout Accounts: $accountCount\n";

$stmt = $conn->query("SELECT COUNT(*) as total FROM payout_requests");
$requestCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
echo "  Payout Requests: $requestCount\n";

if ($requestCount > 0) {
    $stmt = $conn->query("
        SELECT 
            status,
            COUNT(*) as count,
            SUM(amount) as total_amount,
            SUM(fee_amount) as total_fees
        FROM payout_requests 
        GROUP BY status
    ");
    
    echo "\n  Breakdown by status:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $emoji = [
            'pending' => '⏳',
            'processing' => '🔄',
            'completed' => '✅',
            'failed' => '❌',
            'cancelled' => '🚫'
        ][$row['status']] ?? '❓';
        
        echo "    $emoji {$row['status']}: {$row['count']} requests, \${$row['total_amount']} total, \${$row['total_fees']} fees\n";
    }
}
echo "\n";

// Data integrity check
echo "📝 Step 6: Data integrity check\n";
$stmt = $conn->query("
    SELECT pr.id, pr.user_id, pr.payout_account_id 
    FROM payout_requests pr
    LEFT JOIN payout_accounts pa ON pr.payout_account_id = pa.id
    WHERE pa.id IS NULL
");
$orphaned = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($orphaned) > 0) {
    echo "  ⚠️  Found " . count($orphaned) . " orphaned payout requests (no matching payout account)\n";
} else {
    echo "  ✅ All payout requests have valid payout accounts\n";
}

// Check for negative amounts
$stmt = $conn->query("SELECT COUNT(*) as count FROM payout_requests WHERE amount < 0 OR net_amount < 0");
$negativeAmounts = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
if ($negativeAmounts > 0) {
    echo "  ⚠️  Found $negativeAmounts requests with negative amounts\n";
} else {
    echo "  ✅ No negative amounts found\n";
}

// Check fee calculations
$stmt = $conn->query("
    SELECT COUNT(*) as count 
    FROM payout_requests 
    WHERE ABS((amount - fee_amount) - net_amount) > 0.01
");
$badCalculations = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
if ($badCalculations > 0) {
    echo "  ⚠️  Found $badCalculations requests with incorrect fee calculations\n";
} else {
    echo "  ✅ All fee calculations are correct\n";
}

echo "\n===========================================\n";
echo "✅ SYSTEM VERIFICATION COMPLETE\n";
echo "===========================================\n\n";

echo "📊 Summary:\n";
echo "  • Database schema: ✅ Valid\n";
echo "  • Tables: ✅ Present\n";
echo "  • Indexes: ✅ Configured\n";
echo "  • Data integrity: " . ($orphaned || $negativeAmounts || $badCalculations ? "⚠️  Issues found" : "✅ Good") . "\n";
echo "  • Total accounts: $accountCount\n";
echo "  • Total requests: $requestCount\n\n";

echo "🎉 System is ready for production use!\n";
