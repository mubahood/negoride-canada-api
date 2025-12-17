# 🚀 Wallet & Transaction System - Quick Reference Guide

## 🎯 Overview
Every trip payment is automatically split:
- **Driver:** 90% → Credited to driver wallet
- **Company:** 10% → Credited to company wallet (user_id = 1)

## 📋 Key Features
✅ Auto wallet creation when user registers  
✅ Automatic payment distribution on payment success  
✅ 2 transactions created per payment (driver + company)  
✅ Idempotency protection (no duplicates)  
✅ Complete audit trail  
✅ Balance integrity guaranteed  

## 🔧 How To Use

### Check User Wallet Balance
```php
$user = User::find($userId);
$wallet = $user->wallet; // or $user->getOrCreateWallet()

echo "Balance: $" . $wallet->wallet_balance;
echo "Total Earnings: $" . $wallet->total_earnings;
```

### Get Company Wallet
```php
$companyWallet = Negotiation::getOrCreateCompanyWallet();
echo "Company Commission Balance: $" . $companyWallet->wallet_balance;
```

### View Transaction History
```php
// Driver's transactions
$transactions = Transaction::where('user_id', $driverId)
    ->where('category', 'ride_earning')
    ->orderBy('created_at', 'desc')
    ->get();

// Company transactions (commissions)
$commissions = Transaction::where('user_id', 1)
    ->where('category', 'service_fee')
    ->orderBy('created_at', 'desc')
    ->get();
```

### Get Transactions for Specific Trip
```php
$transactions = Transaction::where('negotiation_id', $negotiationId)->get();
// Returns 2 transactions: driver earning + company commission
```

## 📊 Database Queries

### Total Driver Earnings
```sql
SELECT SUM(amount) FROM transactions 
WHERE category = 'ride_earning' 
AND user_id = {driver_id};
```

### Total Company Commission
```sql
SELECT SUM(amount) FROM transactions 
WHERE category = 'service_fee' 
AND user_id = 1;
```

### Today's Transactions
```sql
SELECT * FROM transactions 
WHERE DATE(created_at) = CURDATE()
ORDER BY created_at DESC;
```

### Verify Payment Split
```sql
SELECT 
    n.id,
    n.agreed_price as total,
    t1.amount as driver_90,
    t2.amount as company_10
FROM negotiations n
LEFT JOIN transactions t1 ON n.id = t1.negotiation_id AND t1.category = 'ride_earning'
LEFT JOIN transactions t2 ON n.id = t2.negotiation_id AND t2.category = 'service_fee'
WHERE n.payment_status = 'paid'
ORDER BY n.id DESC
LIMIT 10;
```

## 🔍 Monitoring

### Check for Missing Distributions
```sql
SELECT n.* FROM negotiations n
LEFT JOIN transactions t ON n.id = t.negotiation_id
WHERE n.payment_status = 'paid'
AND t.id IS NULL;
```
*Should return 0 rows*

### Verify Balance Integrity
```sql
SELECT 
    user_id,
    wallet_balance,
    (SELECT SUM(amount) FROM transactions WHERE user_id = w.user_id AND type = 'credit') as total_credits,
    (SELECT SUM(amount) FROM transactions WHERE user_id = w.user_id AND type = 'debit') as total_debits
FROM user_wallets w;
```
*wallet_balance should equal (total_credits - total_debits)*

## 🐛 Troubleshooting

### Wallet not created?
```php
// Manually create for existing user
$user = User::find($userId);
$wallet = $user->getOrCreateWallet();
```

### Distribution didn't happen?
Check logs:
```bash
tail -f storage/logs/laravel.log | grep "distribute\|wallet"
```

### Wrong amounts?
```php
$negotiation = Negotiation::find($id);
echo "Total: " . $negotiation->agreed_price . "\n";
echo "Driver should get: " . ($negotiation->agreed_price * 0.90) . "\n";
echo "Company should get: " . ($negotiation->agreed_price * 0.10) . "\n";

$transactions = Transaction::where('negotiation_id', $id)->get();
foreach ($transactions as $txn) {
    echo "{$txn->category}: {$txn->amount}\n";
}
```

## 📱 API Endpoints (Future)

These can be added if needed:

```php
// Get wallet balance
GET /api/wallet/balance

// Get transaction history
GET /api/wallet/transactions?page=1

// Get specific transaction
GET /api/transactions/{id}

// Get earnings summary
GET /api/wallet/summary
```

## 🎨 Transaction Categories

| Category | Type | Description |
|----------|------|-------------|
| `ride_earning` | credit | Driver earning from completed trip (90%) |
| `service_fee` | credit | Company commission (10%) |
| `ride_payment` | debit | Customer payment for ride |
| `refund` | credit | Refund to customer |
| `wallet_topup` | credit | Manual wallet topup |
| `withdrawal` | debit | Driver withdrawal |
| `bonus` | credit | Promotional bonus |
| `penalty` | debit | Administrative penalty |

## 🔐 Important Constants

```php
// Company wallet user_id
$companyUserId = 1;

// Split percentages
$driverPercentage = 0.90; // 90%
$companyPercentage = 0.10; // 10%

// Transaction statuses
'pending', 'completed', 'failed', 'reversed'

// Transaction types
'credit' // Money added
'debit'  // Money deducted
```

## ✅ Testing

### Run comprehensive test
```bash
cd /Applications/MAMP/htdocs/negoride-canada-api
php test_wallet_distribution.php
```

### Quick manual test
```php
// Create test driver
$driver = User::create([...]);

// Create test negotiation
$neg = Negotiation::create([
    'driver_id' => $driver->id,
    'agreed_price' => 100.00,
    ...
]);

// Mark as paid (triggers distribution)
$neg->markAsPaid();

// Check results
$driver->wallet->refresh();
echo $driver->wallet->wallet_balance; // Should be 90.00

$company = Negotiation::getOrCreateCompanyWallet();
echo $company->wallet_balance; // Should include 10.00
```

## 📈 Reports

### Driver Earnings Report
```sql
SELECT 
    u.name as driver_name,
    COUNT(t.id) as total_trips,
    SUM(t.amount) as total_earnings
FROM admin_users u
JOIN transactions t ON u.id = t.user_id
WHERE t.category = 'ride_earning'
GROUP BY u.id
ORDER BY total_earnings DESC;
```

### Company Commission Report
```sql
SELECT 
    DATE(t.created_at) as date,
    COUNT(t.id) as trips,
    SUM(t.amount) as commission
FROM transactions t
WHERE t.category = 'service_fee'
GROUP BY DATE(t.created_at)
ORDER BY date DESC;
```

## 💡 Best Practices

1. **Always use** `getOrCreateWallet()` instead of direct wallet queries
2. **Never manually** edit wallet balances - always use transactions
3. **Check logs** after each payment to verify distribution
4. **Monitor** for transactions without negotiation_id (orphaned)
5. **Backup** transactions table regularly for audit purposes

## 🆘 Support

For issues or questions:
1. Check logs: `storage/logs/laravel.log`
2. Run test script: `php test_wallet_distribution.php`
3. Verify database integrity queries above
4. Review implementation docs

---

**Last Updated:** December 17, 2025  
**Version:** 1.0.0  
**Status:** Production Ready ✅
