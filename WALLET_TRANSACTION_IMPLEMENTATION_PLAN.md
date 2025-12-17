# Wallet & Transaction Distribution System - Implementation Plan

## 📊 Current State Analysis

### Existing Components ✅
1. **UserWallet Model** - Tracks user balances and earnings
2. **Transaction Model** - Records all financial movements
3. **Negotiation Model** - Handles trip negotiations and payments
4. **Payment System** - Stripe integration for collecting payments
5. **Webhook Handler** - Processes Stripe payment events

### Current Payment Flow
```
Customer → Stripe Payment → Webhook → Negotiation.markAsPaid()
```

**Problem:** Currently, when payment is marked as paid:
- ❌ No wallet balance updates
- ❌ No transaction records created
- ❌ No commission split (90% driver / 10% company)
- ❌ No automatic wallet creation for new drivers

---

## 🎯 Implementation Requirements

### 1. Wallet Auto-Creation
- Create wallet automatically when a new user/driver is created
- Use Laravel model event `created` hook

### 2. Payment Distribution (90/10 Split)
When payment is completed:
- **Driver receives:** 90% of fare → Credit to driver wallet
- **Company receives:** 10% of fare → Credit to company wallet
- **Create 2 transactions:**
  1. Driver transaction (ride_earning) - Credit
  2. Company transaction (service_fee) - Credit

### 3. Transaction Linking
- Both transactions linked to same negotiation
- Reference payment event
- Maintain audit trail with balance tracking

---

## 🔧 Implementation Strategy

### Phase 1: Wallet Auto-Creation
**File:** `app/Models/User.php`
- Add `boot()` method with `created` event
- Auto-create UserWallet for new users
- Set initial balance to 0

### Phase 2: Company Wallet Setup
**Create:** Company wallet record
- ID: 1 (reserved for company)
- Track all commission earnings

###Phase 3: Transaction Distribution Logic
**File:** `app/Models/Negotiation.php`
- Enhance `markAsPaid()` method
- Add `distributePayment()` method
- Calculate 90/10 split
- Create transactions
- Update wallet balances

### Phase 4: Model Events
**Use Laravel Events:**
- `Negotiation::updated` event when payment_status changes to 'paid'
- Trigger wallet distribution automatically
- Prevent duplicate processing

### Phase 5: Testing
- Create test script with dummy data
- Test wallet creation
- Test payment distribution
- Verify transaction creation
- Check balance calculations

---

## 📋 Database Schema

### user_wallets table (existing)
```sql
- id
- user_id (unique)
- wallet_balance (decimal 10,2)
- total_earnings (decimal 10,2)
- stripe_customer_id
- stripe_account_id
- timestamps
```

### transactions table (existing)
```sql
- id
- user_id
- user_type (customer/driver)
- payment_id (nullable)
- type (credit/debit)
- category (ride_earning, service_fee, etc)
- amount
- balance_before
- balance_after
- reference (unique)
- description
- status
- related_user_id
- negotiation_id
- metadata
- timestamps
- soft_deletes
```

---

## 💰 Transaction Flow

### Payment Completion Trigger
```
Stripe Webhook → markAsPaid() → distributePayment() → Create Transactions → Update Wallets
```

### Detailed Steps
1. **Webhook receives payment success**
2. **Negotiation::markAsPaid()** called
3. **Check if payment already distributed** (idempotency)
4. **Calculate amounts:**
   - Total: $agreed_price
   - Driver: $agreed_price * 0.90
   - Company: $agreed_price * 0.10
5. **Get or create wallets:**
   - Driver wallet
   - Company wallet (ID = 1)
6. **Record balance before:**
   - Driver current balance
   - Company current balance
7. **Create Transaction #1 (Driver Earning):**
   - user_id: driver_id
   - user_type: 'driver'
   - type: 'credit'
   - category: 'ride_earning'
   - amount: driver_amount (90%)
   - balance_before: driver old balance
   - balance_after: driver new balance
   - negotiation_id: negotiation.id
   - related_user_id: customer_id
   - reference: TXN-{timestamp}-{negotiation_id}-DRIVER
   - description: "Ride earning from trip #{negotiation_id}"
8. **Create Transaction #2 (Company Commission):**
   - user_id: 1 (company)
   - user_type: 'driver' (company acts as driver for system)
   - type: 'credit'
   - category: 'service_fee'
   - amount: company_amount (10%)
   - balance_before: company old balance
   - balance_after: company new balance
   - negotiation_id: negotiation.id
   - related_user_id: customer_id
   - reference: TXN-{timestamp}-{negotiation_id}-COMPANY
   - description: "Service fee from trip #{negotiation_id} (10%)"
9. **Update Wallets:**
   - Driver: wallet_balance += driver_amount
   - Driver: total_earnings += driver_amount
   - Company: wallet_balance += company_amount
   - Company: total_earnings += company_amount
10. **Mark as distributed:**
    - Set flag to prevent duplicate distribution

---

## 🛡️ Security & Data Integrity

### Idempotency Protection
- Check if transactions already exist for this negotiation
- Use unique transaction references
- Database transaction (DB::transaction)

### Validation
- Ensure agreed_price > 0
- Verify driver exists
- Confirm wallet exists or create
- Validate amounts sum correctly

### Error Handling
- Wrap in try-catch
- Log all distribution events
- Rollback on failure
- Notify admins of failures

---

## 📝 Code Structure

### New Methods

#### User Model
```php
protected static function boot()
{
    parent::boot();
    
    static::created(function ($user) {
        // Auto-create wallet
        UserWallet::create([
            'user_id' => $user->id,
            'wallet_balance' => 0,
            'total_earnings' => 0,
        ]);
    });
}
```

#### Negotiation Model
```php
public function distributePayment(): bool
{
    // 1. Check if already distributed
    // 2. Calculate amounts (90/10)
    // 3. Get wallets
    // 4. Create transactions
    // 5. Update balances
    // 6. Log success
}

private function createDriverTransaction($amount, $driverWallet): Transaction
{
    // Create driver earning transaction
}

private function createCompanyTransaction($amount, $companyWallet): Transaction
{
    // Create company commission transaction
}

private function hasPaymentBeenDistributed(): bool
{
    // Check if transactions exist for this negotiation
}

public static function getOrCreateCompanyWallet(): UserWallet
{
    // Get or create company wallet (user_id = 1)
}
```

---

## 🧪 Testing Plan

### Test Script: `test_wallet_distribution.php`
1. Create test driver
2. Create test customer  
3. Create test negotiation with agreed_price
4. Simulate payment completion
5. Verify:
   - Driver wallet created automatically
   - Driver balance increased by 90%
   - Company balance increased by 10%
   - 2 transactions created and linked
   - Balances match transaction records
   - Idempotency (calling twice doesn't duplicate)

### Edge Cases
- Negotiation with $0 price
- Missing driver
- Wallet creation failure
- Concurrent payment completions
- Refund scenarios (future)

---

## 📊 Success Metrics

### Must Achieve
✅ Auto-wallet creation for all new users
✅ 100% payment distribution accuracy
✅ 90/10 split enforced
✅ 2 transactions per payment (driver + company)
✅ Balance integrity maintained
✅ Zero duplicate distributions
✅ Complete audit trail

---

## 🚀 Deployment Checklist

- [ ] Review all code changes
- [ ] Run tests with dummy data
- [ ] Verify database constraints
- [ ] Check log output
- [ ] Test idempotency
- [ ] Simulate webhook events
- [ ] Monitor first real payments
- [ ] Document for team

---

**Implementation Date:** December 17, 2025
**Status:** Ready to Implement
