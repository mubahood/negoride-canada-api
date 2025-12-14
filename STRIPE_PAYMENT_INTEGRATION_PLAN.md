# Stripe Payment Integration Plan - Negoride Canada

**Project:** Negoride Canada Rideshare Platform  
**Feature:** Stripe Payment Integration  
**Date:** December 14, 2025  
**Version:** 1.0

---

## 📋 Executive Summary

This document outlines the comprehensive plan to integrate Stripe payment processing into the Negoride Canada platform. The integration will handle payments for completed ride negotiations with full transaction tracking for both customers and drivers.

---

## 🎯 Objectives

1. **Secure Payment Processing**: Integrate Stripe for CAD currency payments
2. **Payment Tracking**: Track payment status in negotiations
3. **Transaction History**: Maintain complete transaction records for customers and drivers
4. **Wallet System**: Implement driver wallet/balance tracking
5. **Centralized Payment Module**: Create reusable payment processing module
6. **Mobile Integration**: Seamless payment flow in Flutter app

---

## 🏗️ Architecture Overview

### **Components to Build:**
1. **Backend (Laravel):**
   - Payment Model (centralized)
   - Transaction Model (history tracking)
   - Negotiation Model Updates (payment fields)
   - Payment Controller
   - Stripe Service Class
   - Migration Files
   - API Endpoints

2. **Mobile App (Flutter):**
   - Payment Screen
   - Stripe SDK Integration
   - Payment Status UI
   - Transaction History Screen

---

## 📊 Current System Analysis

### **Existing Database Structure:**
```
negotiations
├── id
├── customer_id
├── driver_id
├── status (Active, Accepted, Started, Completed, Canceled)
├── pickup_address
├── dropoff_address
├── is_active
└── ... (location fields)

negotiation_records
├── id
├── negotiation_id
├── message_body
├── price
└── ... (chat fields)

admin_users (customers & drivers)
├── id
├── name
├── phone_number
├── ready_for_trip
└── ... (user fields)
```

### **Payment Flow Points:**
1. ✅ Negotiation Created
2. ✅ Price Agreed (status: Accepted)
3. ✅ Trip Started (status: Started)
4. ✅ Trip Completed (status: Completed)
5. ⚠️ **Payment Initiated** ← NEW
6. ⚠️ **Payment Completed** ← NEW
7. ⚠️ **Payment Failed** ← NEW

---

## 📝 Detailed Implementation Plan

---

## **PHASE 1: Backend Database Schema**

### **Task 1.1: Create Payments Table**
**File:** `database/migrations/YYYY_MM_DD_create_payments_table.php`

**Purpose:** Centralized payment processing records

**Fields:**
```php
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->timestamps();
    
    // Reference Information
    $table->string('payment_type')->default('negotiation'); // negotiation, trip_booking, etc.
    $table->unsignedBigInteger('reference_id'); // ID of negotiation/booking
    $table->string('reference_model')->nullable(); // Model class name
    
    // Amount Details
    $table->decimal('amount', 10, 2); // CAD amount
    $table->string('currency')->default('CAD');
    $table->decimal('service_fee', 10, 2)->default(0); // Platform fee
    $table->decimal('driver_amount', 10, 2); // Amount driver receives
    
    // User Information
    $table->unsignedBigInteger('customer_id');
    $table->string('customer_name')->nullable();
    $table->unsignedBigInteger('driver_id');
    $table->string('driver_name')->nullable();
    
    // Stripe Information
    $table->string('stripe_payment_intent_id')->unique()->nullable();
    $table->string('stripe_payment_method_id')->nullable();
    $table->string('stripe_customer_id')->nullable();
    $table->text('stripe_response')->nullable(); // JSON response
    
    // Status Tracking
    $table->string('status')->default('pending'); // pending, processing, completed, failed, refunded
    $table->string('payment_method')->default('stripe'); // stripe, cash, etc.
    $table->timestamp('paid_at')->nullable();
    $table->timestamp('failed_at')->nullable();
    
    // Error Handling
    $table->text('error_message')->nullable();
    $table->integer('retry_count')->default(0);
    
    // Metadata
    $table->text('description')->nullable();
    $table->json('metadata')->nullable();
    
    // Indexes
    $table->index('customer_id');
    $table->index('driver_id');
    $table->index('reference_id');
    $table->index('status');
    $table->index('stripe_payment_intent_id');
});
```

**Estimated Time:** 30 minutes  
**Dependencies:** None  
**Testing:** Migration runs successfully

---

### **Task 1.2: Create Transactions Table**
**File:** `database/migrations/YYYY_MM_DD_create_transactions_table.php`

**Purpose:** Complete transaction history for customers and drivers

**Fields:**
```php
Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    $table->timestamps();
    
    // User Information
    $table->unsignedBigInteger('user_id'); // Can be customer or driver
    $table->string('user_type')->default('customer'); // customer, driver
    $table->string('user_name')->nullable();
    
    // Transaction Details
    $table->enum('type', ['credit', 'debit']); // + or -
    $table->decimal('amount', 10, 2);
    $table->string('currency')->default('CAD');
    $table->decimal('balance_before', 10, 2)->default(0);
    $table->decimal('balance_after', 10, 2)->default(0);
    
    // Reference Information
    $table->string('reference_type')->nullable(); // payment, refund, withdrawal, etc.
    $table->unsignedBigInteger('reference_id')->nullable();
    $table->unsignedBigInteger('payment_id')->nullable(); // Link to payments table
    
    // Description
    $table->string('title'); // E.g., "Ride Payment", "Driver Earnings"
    $table->text('description')->nullable();
    $table->string('status')->default('completed'); // completed, pending, failed
    
    // Metadata
    $table->json('metadata')->nullable();
    
    // Indexes
    $table->index('user_id');
    $table->index('type');
    $table->index('payment_id');
    $table->index('status');
    $table->index(['user_id', 'created_at']);
});
```

**Estimated Time:** 30 minutes  
**Dependencies:** None  
**Testing:** Migration runs successfully

---

### **Task 1.3: Add Payment Fields to Negotiations Table**
**File:** `database/migrations/YYYY_MM_DD_add_payment_fields_to_negotiations.php`

**Purpose:** Track payment status within negotiations

**Fields:**
```php
Schema::table('negotiations', function (Blueprint $table) {
    // Payment Information
    $table->decimal('agreed_price', 10, 2)->nullable()->after('details');
    $table->string('payment_status')->default('pending')->after('agreed_price'); 
    // pending, paid, failed, refunded
    $table->unsignedBigInteger('payment_id')->nullable()->after('payment_status');
    $table->timestamp('payment_completed_at')->nullable()->after('payment_id');
    
    // Indexes
    $table->index('payment_status');
    $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
});
```

**Estimated Time:** 20 minutes  
**Dependencies:** Task 1.1 (Payments table)  
**Testing:** Migration runs, foreign key works

---

### **Task 1.4: Add Balance Field to Admin Users Table**
**File:** `database/migrations/YYYY_MM_DD_add_wallet_fields_to_admin_users.php`

**Purpose:** Track driver wallet balance

**Fields:**
```php
Schema::table('admin_users', function (Blueprint $table) {
    // Wallet/Balance
    $table->decimal('wallet_balance', 10, 2)->default(0)->after('ready_for_trip');
    $table->decimal('total_earnings', 10, 2)->default(0)->after('wallet_balance');
    $table->decimal('total_withdrawals', 10, 2)->default(0)->after('total_earnings');
    
    // Stripe Connect (for future driver payouts)
    $table->string('stripe_account_id')->nullable()->after('total_withdrawals');
    $table->string('stripe_customer_id')->nullable()->after('stripe_account_id');
    
    // Indexes
    $table->index('wallet_balance');
});
```

**Estimated Time:** 20 minutes  
**Dependencies:** None  
**Testing:** Migration runs successfully

---

## **PHASE 2: Backend Models**

### **Task 2.1: Create Payment Model**
**File:** `app/Models/Payment.php`

**Features:**
- Eloquent relationships (customer, driver, negotiation)
- Status management methods
- Stripe integration helpers
- Scopes for filtering
- Automatic transaction creation

**Key Methods:**
```php
class Payment extends Model
{
    // Relationships
    public function customer();
    public function driver();
    public function negotiation();
    public function transactions();
    
    // Status Methods
    public function markAsPaid();
    public function markAsFailed($errorMessage);
    public function markAsRefunded();
    
    // Stripe Methods
    public function createStripePaymentIntent();
    public function confirmStripePayment($paymentIntentId);
    
    // Scopes
    public function scopePending($query);
    public function scopeCompleted($query);
    public function scopeByCustomer($query, $customerId);
    
    // Helpers
    public function calculateDriverAmount(); // After service fee
    protected static function boot(); // Auto-create transactions
}
```

**Estimated Time:** 2 hours  
**Dependencies:** Task 1.1  
**Testing:** Create payment record, test relationships

---

### **Task 2.2: Create Transaction Model**
**File:** `app/Models/Transaction.php`

**Features:**
- User polymorphic relationship
- Balance calculation
- Transaction types
- History retrieval

**Key Methods:**
```php
class Transaction extends Model
{
    // Relationships
    public function user();
    public function payment();
    
    // Static Creation Methods
    public static function createForPayment($payment);
    public static function createCredit($userId, $amount, $details);
    public static function createDebit($userId, $amount, $details);
    
    // Scopes
    public function scopeByUser($query, $userId);
    public function scopeCredits($query);
    public function scopeDebits($query);
    
    // Helpers
    public function updateUserBalance();
    protected static function boot(); // Auto-update balances
}
```

**Estimated Time:** 1.5 hours  
**Dependencies:** Task 1.2  
**Testing:** Create transactions, verify balance updates

---

### **Task 2.3: Update Negotiation Model**
**File:** `app/Models/Negotiation.php`

**Updates:**
- Add payment relationship
- Add payment status methods
- Update fillable fields

**New Methods:**
```php
// In Negotiation.php
public function payment();
public function requiresPayment();
public function markAsPaid($paymentId);
public function canBeCompleted(); // Check payment status
```

**Estimated Time:** 30 minutes  
**Dependencies:** Task 1.3, Task 2.1  
**Testing:** Load negotiation with payment

---

## **PHASE 3: Stripe Service Layer**

### **Task 3.1: Install Stripe PHP SDK**
**Command:**
```bash
composer require stripe/stripe-php:^13.2
```

**Update .env:**
```env
# Stripe Configuration
STRIPE_KEY=sk_test_... # Secret key (backend)
STRIPE_PUBLISHABLE_KEY=pk_test_... # Publishable key (mobile app)
STRIPE_WEBHOOK_SECRET=whsec_... # Webhook signing secret
STRIPE_CURRENCY=cad # Lowercase required by Stripe
STRIPE_SERVICE_FEE_PERCENTAGE=10 # Platform commission (10%)
STRIPE_API_VERSION=2023-10-16 # API version for consistency
```

**Why Payment Intents over Payment Links:**
- ✅ **Mobile-native**: Works seamlessly with Flutter Stripe SDK
- ✅ **Better UX**: In-app payment, no browser redirect
- ✅ **Real-time**: Instant confirmation via webhooks
- ✅ **Secure**: 3D Secure & SCA compliant
- ✅ **Flexible**: Supports saved payment methods
- ❌ Payment Links: Requires browser, slower, not mobile-optimized

**Estimated Time:** 15 minutes  
**Dependencies:** None  
**Testing:** Verify package installed with `composer show stripe/stripe-php`

---

### **Task 3.2: Create Stripe Service Class**
**File:** `app/Services/StripeService.php`

**Purpose:** Centralized, production-ready Stripe API interactions

**Implementation (Improved from existing code):**

```php
<?php

namespace App\Services;

use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;

class StripeService
{
    protected StripeClient $stripe;
    protected string $currency;
    protected float $serviceFeePercentage;
    
    public function __construct()
    {
        $this->stripe = new StripeClient(env('STRIPE_KEY'));
        $this->currency = strtolower(env('STRIPE_CURRENCY', 'cad'));
        $this->serviceFeePercentage = (float) env('STRIPE_SERVICE_FEE_PERCENTAGE', 10);
        
        // Set API version for consistency
        $this->stripe->setApiVersion(env('STRIPE_API_VERSION', '2023-10-16'));
    }
    
    /**
     * Create Payment Intent (Better than Payment Links for mobile)
     * 
     * @param float $amount Amount in CAD (will be converted to cents)
     * @param array $metadata Additional data (negotiation_id, customer_id, etc.)
     * @param string|null $customerId Stripe customer ID (for saved cards)
     * @return array ['payment_intent_id', 'client_secret', 'amount']
     */
    public function createPaymentIntent(
        float $amount, 
        array $metadata = [], 
        ?string $customerId = null
    ): array {
        try {
            // Convert to cents (Stripe requirement)
            $amountInCents = $this->formatAmount($amount);
            
            // Validate minimum amount (CAD $0.50)
            if ($amountInCents < 50) {
                throw new \Exception('Amount must be at least CAD $0.50');
            }
            
            $intentData = [
                'amount' => $amountInCents,
                'currency' => $this->currency,
                'metadata' => array_merge($metadata, [
                    'app' => 'Negoride Canada',
                    'platform' => 'mobile',
                    'created_at' => now()->toIso8601String(),
                ]),
                'description' => $metadata['description'] ?? 'Ride Payment',
                
                // Important: Enable automatic payment methods
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                
                // Capture method (automatic is recommended)
                'capture_method' => 'automatic',
                
                // Receipt email (optional)
                'receipt_email' => $metadata['customer_email'] ?? null,
            ];
            
            // Add customer if provided (for saved payment methods)
            if ($customerId) {
                $intentData['customer'] = $customerId;
            }
            
            $paymentIntent = $this->stripe->paymentIntents->create($intentData);
            
            Log::info('Stripe Payment Intent Created', [
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $amount,
                'metadata' => $metadata,
            ]);
            
            return [
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
                'amount' => $amount,
                'amount_cents' => $amountInCents,
                'status' => $paymentIntent->status,
            ];
            
        } catch (ApiErrorException $e) {
            return $this->handleStripeError($e);
        } catch (\Exception $e) {
            Log::error('Payment Intent Creation Failed', [
                'error' => $e->getMessage(),
                'amount' => $amount,
            ]);
            throw $e;
        }
    }
    
    /**
     * Retrieve Payment Intent Status
     * 
     * @param string $paymentIntentId
     * @return array ['status', 'amount', 'metadata']
     */
    public function retrievePaymentIntent(string $paymentIntentId): array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);
            
            return [
                'id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount / 100, // Convert back to dollars
                'currency' => $paymentIntent->currency,
                'metadata' => $paymentIntent->metadata->toArray(),
                'created' => $paymentIntent->created,
                'payment_method' => $paymentIntent->payment_method,
            ];
            
        } catch (ApiErrorException $e) {
            return $this->handleStripeError($e);
        }
    }
    
    /**
     * Confirm Payment Intent (Usually done by mobile SDK, but useful for verification)
     * 
     * @param string $paymentIntentId
     * @return bool
     */
    public function confirmPaymentIntent(string $paymentIntentId): bool
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);
            
            // Check if already succeeded
            if ($paymentIntent->status === 'succeeded') {
                return true;
            }
            
            // Confirm if needed
            if ($paymentIntent->status === 'requires_confirmation') {
                $this->stripe->paymentIntents->confirm($paymentIntentId);
                return true;
            }
            
            return false;
            
        } catch (ApiErrorException $e) {
            Log::error('Payment Confirmation Failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Cancel Payment Intent (if needed before completion)
     * 
     * @param string $paymentIntentId
     * @return bool
     */
    public function cancelPaymentIntent(string $paymentIntentId): bool
    {
        try {
            $this->stripe->paymentIntents->cancel($paymentIntentId);
            
            Log::info('Payment Intent Canceled', [
                'payment_intent_id' => $paymentIntentId,
            ]);
            
            return true;
            
        } catch (ApiErrorException $e) {
            Log::error('Payment Cancellation Failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Create or Retrieve Stripe Customer (for saved payment methods)
     * 
     * @param int $userId
     * @param string $email
     * @param string $name
     * @return string Stripe customer ID
     */
    public function createOrGetCustomer(int $userId, string $email, string $name): string
    {
        try {
            // Try to find existing customer
            $customers = $this->stripe->customers->search([
                'query' => "metadata['user_id']:'{$userId}'",
                'limit' => 1,
            ]);
            
            if ($customers->data && count($customers->data) > 0) {
                return $customers->data[0]->id;
            }
            
            // Create new customer
            $customer = $this->stripe->customers->create([
                'email' => $email,
                'name' => $name,
                'metadata' => [
                    'user_id' => $userId,
                    'app' => 'Negoride Canada',
                ],
            ]);
            
            Log::info('Stripe Customer Created', [
                'customer_id' => $customer->id,
                'user_id' => $userId,
            ]);
            
            return $customer->id;
            
        } catch (ApiErrorException $e) {
            throw $this->handleStripeError($e);
        }
    }
    
    /**
     * Create Refund (for canceled rides)
     * 
     * @param string $paymentIntentId
     * @param float|null $amount Optional partial refund amount
     * @param string|null $reason
     * @return array ['refund_id', 'status', 'amount']
     */
    public function createRefund(
        string $paymentIntentId, 
        ?float $amount = null,
        ?string $reason = null
    ): array {
        try {
            $refundData = [
                'payment_intent' => $paymentIntentId,
            ];
            
            // Partial refund if amount specified
            if ($amount !== null) {
                $refundData['amount'] = $this->formatAmount($amount);
            }
            
            // Add reason if provided
            if ($reason) {
                $refundData['reason'] = $reason;
                $refundData['metadata'] = ['reason_detail' => $reason];
            }
            
            $refund = $this->stripe->refunds->create($refundData);
            
            Log::info('Refund Created', [
                'refund_id' => $refund->id,
                'payment_intent_id' => $paymentIntentId,
                'amount' => $amount,
            ]);
            
            return [
                'refund_id' => $refund->id,
                'status' => $refund->status,
                'amount' => $refund->amount / 100,
                'currency' => $refund->currency,
            ];
            
        } catch (ApiErrorException $e) {
            return $this->handleStripeError($e);
        }
    }
    
    /**
     * Verify Webhook Signature (Security critical!)
     * 
     * @param string $payload Request body
     * @param string $signature Stripe-Signature header
     * @return \Stripe\Event|null
     */
    public function verifyWebhookSignature(string $payload, string $signature): ?\Stripe\Event
    {
        try {
            $webhookSecret = env('STRIPE_WEBHOOK_SECRET');
            
            if (!$webhookSecret) {
                throw new \Exception('Webhook secret not configured');
            }
            
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $webhookSecret
            );
            
            return $event;
            
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Webhook Signature Verification Failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    
    /**
     * Handle Webhook Event (Process different event types)
     * 
     * @param \Stripe\Event $event
     * @return bool
     */
    public function handleWebhookEvent(\Stripe\Event $event): bool
    {
        try {
            Log::info('Webhook Event Received', [
                'type' => $event->type,
                'id' => $event->id,
            ]);
            
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    return $this->handlePaymentSuccess($event->data->object);
                    
                case 'payment_intent.payment_failed':
                    return $this->handlePaymentFailed($event->data->object);
                    
                case 'charge.refunded':
                    return $this->handleRefund($event->data->object);
                    
                case 'customer.created':
                    Log::info('Customer Created', ['id' => $event->data->object->id]);
                    return true;
                    
                default:
                    Log::info('Unhandled Webhook Event', ['type' => $event->type]);
                    return true;
            }
            
        } catch (\Exception $e) {
            Log::error('Webhook Event Handling Failed', [
                'event_type' => $event->type,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Handle successful payment webhook
     */
    protected function handlePaymentSuccess($paymentIntent): bool
    {
        // Find payment in database
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();
        
        if (!$payment) {
            Log::warning('Payment not found for successful intent', [
                'payment_intent_id' => $paymentIntent->id,
            ]);
            return false;
        }
        
        // Mark as paid (will trigger transaction creation via model events)
        $payment->markAsPaid();
        
        return true;
    }
    
    /**
     * Handle failed payment webhook
     */
    protected function handlePaymentFailed($paymentIntent): bool
    {
        $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();
        
        if (!$payment) {
            return false;
        }
        
        $errorMessage = $paymentIntent->last_payment_error->message ?? 'Payment failed';
        $payment->markAsFailed($errorMessage);
        
        return true;
    }
    
    /**
     * Handle refund webhook
     */
    protected function handleRefund($charge): bool
    {
        // Implementation depends on your refund policy
        Log::info('Refund Processed', [
            'charge_id' => $charge->id,
            'amount' => $charge->amount_refunded / 100,
        ]);
        
        return true;
    }
    
    /**
     * Calculate service fee
     * 
     * @param float $amount
     * @return float
     */
    public function calculateServiceFee(float $amount): float
    {
        return round(($amount * $this->serviceFeePercentage) / 100, 2);
    }
    
    /**
     * Calculate driver amount (after service fee)
     * 
     * @param float $amount
     * @return float
     */
    public function calculateDriverAmount(float $amount): float
    {
        $serviceFee = $this->calculateServiceFee($amount);
        return round($amount - $serviceFee, 2);
    }
    
    /**
     * Convert amount to cents (Stripe uses smallest currency unit)
     * 
     * @param float $amount
     * @return int
     */
    protected function formatAmount(float $amount): int
    {
        return (int) round($amount * 100);
    }
    
    /**
     * Handle Stripe API errors with detailed logging
     * 
     * @param ApiErrorException $exception
     * @throws \Exception
     */
    protected function handleStripeError(ApiErrorException $exception): void
    {
        $errorData = [
            'type' => $exception->getStripeCode(),
            'message' => $exception->getMessage(),
            'http_status' => $exception->getHttpStatus(),
        ];
        
        Log::error('Stripe API Error', $errorData);
        
        // User-friendly error messages
        $userMessage = match($exception->getStripeCode()) {
            'card_declined' => 'Your card was declined. Please try another payment method.',
            'expired_card' => 'Your card has expired. Please use a different card.',
            'insufficient_funds' => 'Insufficient funds. Please use another payment method.',
            'invalid_number' => 'Invalid card number. Please check and try again.',
            'incorrect_cvc' => 'Incorrect security code. Please check your card.',
            'processing_error' => 'Payment processing error. Please try again.',
            'rate_limit' => 'Too many requests. Please wait a moment and try again.',
            default => 'Payment failed. Please try again or contact support.',
        };
        
        throw new \Exception($userMessage);
    }
}
```

**Key Improvements over existing code:**

1. ✅ **Payment Intents** instead of Payment Links (mobile-optimized)
2. ✅ **Proper error handling** with user-friendly messages
3. ✅ **Webhook support** for real-time updates
4. ✅ **Service fee calculation** built-in
5. ✅ **Customer management** for saved cards
6. ✅ **Refund support** for canceled rides
7. ✅ **Comprehensive logging** for debugging
8. ✅ **Amount validation** (minimum $0.50)
9. ✅ **Metadata tracking** for all transactions
10. ✅ **Security**: Webhook signature verification

**Estimated Time:** 4 hours  
**Dependencies:** Task 3.1  
**Testing:** Unit tests + Stripe test mode

---

## **PHASE 4: Backend Controllers & API**

### **Task 4.1: Create Payment Controller**
**File:** `app/Http/Controllers/ApiPaymentController.php`

**Endpoints:**
```php
class ApiPaymentController extends Controller
{
    use ApiResponser;
    
    // POST /api/payment/create-intent
    public function createPaymentIntent(Request $request);
    // Input: negotiation_id, amount
    // Output: payment_intent_client_secret, payment_id
    
    // POST /api/payment/confirm
    public function confirmPayment(Request $request);
    // Input: payment_id, payment_intent_id
    // Output: payment status, transaction details
    
    // POST /api/payment/cancel
    public function cancelPayment(Request $request);
    // Input: payment_id
    // Output: cancellation confirmation
    
    // GET /api/payment/status/{negotiation_id}
    public function getPaymentStatus($negotiationId);
    // Output: payment details, status
    
    // GET /api/transactions/history
    public function getTransactionHistory(Request $request);
    // Input: user_id, type (optional)
    // Output: paginated transaction list
    
    // GET /api/wallet/balance
    public function getWalletBalance(Request $request);
    // Output: current balance, total earnings
}
```

**Estimated Time:** 4 hours  
**Dependencies:** Task 2.1, Task 2.2, Task 3.2  
**Testing:** Test each endpoint with Postman

---

### **Task 4.2: Create Webhook Controller**
**File:** `app/Http/Controllers/StripeWebhookController.php`

**Purpose:** Handle Stripe webhook events

**Events to Handle:**
- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `charge.refunded`
- `customer.created`

**Estimated Time:** 2 hours  
**Dependencies:** Task 3.2  
**Testing:** Test with Stripe CLI webhooks

---

### **Task 4.3: Update API Routes**
**File:** `routes/api.php`

**New Routes:**
```php
Route::group(['middleware' => 'auth:api'], function () {
    // Payment Routes
    Route::prefix('payment')->group(function () {
        Route::post('/create-intent', [ApiPaymentController::class, 'createPaymentIntent']);
        Route::post('/confirm', [ApiPaymentController::class, 'confirmPayment']);
        Route::post('/cancel', [ApiPaymentController::class, 'cancelPayment']);
        Route::get('/status/{negotiation_id}', [ApiPaymentController::class, 'getPaymentStatus']);
    });
    
    // Transaction Routes
    Route::prefix('transactions')->group(function () {
        Route::get('/history', [ApiPaymentController::class, 'getTransactionHistory']);
    });
    
    // Wallet Routes
    Route::prefix('wallet')->group(function () {
        Route::get('/balance', [ApiPaymentController::class, 'getWalletBalance']);
    });
});

// Webhook (no auth required)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);
```

**Estimated Time:** 30 minutes  
**Dependencies:** Task 4.1, Task 4.2  
**Testing:** Verify routes with `php artisan route:list`

---

### **Task 4.4: Update Negotiation Accept Endpoint**
**File:** `app/Http/Controllers/ApiChatController.php`

**Update:** `negotiations_accept()` method

**Changes:**
- Capture agreed price from last negotiation record
- Set `agreed_price` in negotiation
- Set `payment_status` to 'pending'
- Return payment requirement status

**Estimated Time:** 1 hour  
**Dependencies:** Task 2.3  
**Testing:** Accept negotiation, verify price captured

---

## **PHASE 5: Backend Testing & Seeding**

### **Task 5.1: Create Database Seeders**
**Files:**
- `database/seeders/PaymentSeeder.php`
- `database/seeders/TransactionSeeder.php`

**Seed Data:**
- 10 sample payments (various statuses)
- 50 sample transactions
- Update user balances

**Estimated Time:** 1.5 hours  
**Dependencies:** All models created  
**Testing:** Run seeders, verify data

---

### **Task 5.2: Create Unit Tests**
**Files:**
- `tests/Unit/PaymentModelTest.php`
- `tests/Unit/TransactionModelTest.php`
- `tests/Unit/StripeServiceTest.php`

**Test Coverage:**
- Payment creation and status changes
- Transaction balance calculations
- Stripe API mocking

**Estimated Time:** 3 hours  
**Dependencies:** All backend components  
**Testing:** `php artisan test`

---

### **Task 5.3: Create Feature Tests**
**Files:**
- `tests/Feature/PaymentApiTest.php`
- `tests/Feature/WebhookTest.php`

**Test Scenarios:**
- Complete payment flow
- Failed payment handling
- Webhook event processing
- Transaction history retrieval

**Estimated Time:** 3 hours  
**Dependencies:** All API endpoints  
**Testing:** `php artisan test --filter Feature`

---

### **Task 5.4: Manual Testing with Postman**
**Collection:** Create Postman collection

**Test Cases:**
1. Create payment intent
2. Confirm payment (success)
3. Simulate payment failure
4. Check payment status
5. View transaction history
6. Check wallet balance
7. Test webhook events

**Estimated Time:** 2 hours  
**Dependencies:** All endpoints ready  
**Testing:** Export Postman collection

---

## **PHASE 6: Mobile App Integration (Flutter)**

### **Task 6.1: Add Stripe Flutter Package**
**File:** `pubspec.yaml`

```yaml
dependencies:
  flutter_stripe: ^10.0.0
```

**Initialize:**
```dart
// In main.dart
await Stripe.instance.applySettings(
  publishableKey: 'pk_test_...',
  merchantIdentifier: 'merchant.ca.negoride',
);
```

**Estimated Time:** 30 minutes  
**Dependencies:** None  
**Testing:** App builds successfully

---

### **Task 6.2: Create Payment Models**
**Files:**
- `lib/models/PaymentModel.dart`
- `lib/models/TransactionModel.dart`

**Features:**
- JSON serialization
- Local database storage
- API integration methods

**Estimated Time:** 2 hours  
**Dependencies:** Task 6.1  
**Testing:** Model serialization works

---

### **Task 6.3: Create Payment Service**
**File:** `lib/services/PaymentService.dart`

**Methods:**
```dart
class PaymentService {
  // Create payment intent
  Future<Map<String, dynamic>> createPaymentIntent(int negotiationId, double amount);
  
  // Confirm payment
  Future<bool> confirmPayment(int paymentId, String paymentIntentId);
  
  // Present payment sheet
  Future<bool> presentPaymentSheet(String clientSecret, double amount);
  
  // Check payment status
  Future<String> getPaymentStatus(int negotiationId);
  
  // Get transaction history
  Future<List<TransactionModel>> getTransactionHistory();
}
```

**Estimated Time:** 3 hours  
**Dependencies:** Task 6.2  
**Testing:** API calls work

---

### **Task 6.4: Create Payment Screen**
**File:** `lib/screens/payments/PaymentScreen.dart`

**Features:**
- Display negotiation details
- Show agreed price
- Stripe payment sheet
- Payment confirmation
- Error handling
- Loading states

**UI Components:**
- Trip summary card
- Price breakdown
- "Pay Now" button
- Payment status indicator

**Estimated Time:** 4 hours  
**Dependencies:** Task 6.3  
**Testing:** Payment flow works end-to-end

---

### **Task 6.5: Update NegotiationScreen**
**File:** `lib/screens/chats/NegotiationScreen.dart`

**Updates:**
- Show "Pay Now" button when status = 'Accepted'
- Show payment status badge
- Navigate to PaymentScreen
- Disable trip start until paid (for customer)
- Show "Waiting for payment" for driver

**Estimated Time:** 2 hours  
**Dependencies:** Task 6.4  
**Testing:** Button shows correctly based on status

---

### **Task 6.6: Create Transaction History Screen**
**File:** `lib/screens/wallet/TransactionHistoryScreen.dart`

**Features:**
- List all transactions
- Filter by type (credit/debit)
- Show balance progression
- Pull-to-refresh
- Pagination

**Estimated Time:** 3 hours  
**Dependencies:** Task 6.3  
**Testing:** Transactions display correctly

---

### **Task 6.7: Create Wallet Screen**
**File:** `lib/screens/wallet/WalletScreen.dart`

**Features:**
- Display current balance
- Total earnings (drivers)
- Transaction summary
- Withdrawal button (future)
- Transaction history link

**Estimated Time:** 2 hours  
**Dependencies:** Task 6.6  
**Testing:** Balance displays correctly

---

### **Task 6.8: Update Negotiation Model (Dart)**
**File:** `lib/models/NegotiationModel.dart`

**Add Fields:**
```dart
double agreed_price = 0.0;
String payment_status = 'pending';
int? payment_id;
String payment_completed_at = '';
```

**Estimated Time:** 30 minutes  
**Dependencies:** Backend updates  
**Testing:** Model parses API response

---

## **PHASE 7: Integration & End-to-End Testing**

### **Task 7.1: Integration Testing**

**Test Complete Flow:**
1. Create negotiation
2. Negotiate price
3. Accept negotiation (price captured)
4. Customer sees "Pay Now" button
5. Customer initiates payment
6. Payment processed via Stripe
7. Payment confirmed in backend
8. Negotiation updated to 'paid'
9. Driver sees "Paid" status
10. Driver can start trip
11. Trip completed
12. Transactions created for both users
13. Balances updated correctly

**Estimated Time:** 4 hours  
**Dependencies:** All components complete  
**Testing:** Document test results

---

### **Task 7.2: Error Scenario Testing**

**Test Cases:**
1. Payment declined by Stripe
2. Network failure during payment
3. Duplicate payment attempt
4. Expired payment intent
5. Insufficient funds
6. Card validation errors

**Estimated Time:** 3 hours  
**Dependencies:** Task 7.1  
**Testing:** All errors handled gracefully

---

### **Task 7.3: Performance Testing**

**Metrics:**
- Payment intent creation time
- Payment confirmation time
- Webhook processing time
- Transaction history load time
- Database query optimization

**Estimated Time:** 2 hours  
**Dependencies:** All features complete  
**Testing:** Performance benchmarks documented

---

## **PHASE 8: Documentation & Deployment**

### **Task 8.1: API Documentation**
**File:** `API_PAYMENT_DOCUMENTATION.md`

**Content:**
- All payment endpoints
- Request/response examples
- Error codes
- Webhook events
- Testing guide

**Estimated Time:** 2 hours  
**Dependencies:** All API complete  
**Testing:** Documentation accurate

---

### **Task 8.2: User Documentation**
**File:** `USER_PAYMENT_GUIDE.md`

**Content:**
- How to make payment
- Payment status meanings
- Transaction history
- Refund policy
- FAQ

**Estimated Time:** 1 hour  
**Dependencies:** None  
**Testing:** Review for clarity

---

### **Task 8.3: Environment Configuration**
**File:** `DEPLOYMENT_CHECKLIST.md`

**Checklist:**
- [ ] Stripe live keys configured
- [ ] Webhook endpoint registered
- [ ] SSL certificate active
- [ ] Database migrations run
- [ ] Seeder data cleared
- [ ] Error logging enabled
- [ ] Payment monitoring setup

**Estimated Time:** 1 hour  
**Dependencies:** None  
**Testing:** Checklist complete

---

### **Task 8.4: Code Review & Cleanup**

**Items:**
- Remove debug code
- Optimize queries
- Add code comments
- Follow PSR standards
- Flutter code formatting
- Remove unused imports

**Estimated Time:** 3 hours  
**Dependencies:** All code complete  
**Testing:** Code quality check

---

## 📊 Summary & Timeline

### **Total Estimated Time:**

| Phase | Tasks | Estimated Hours |
|-------|-------|-----------------|
| Phase 1: Database Schema | 4 | 2 hours |
| Phase 2: Backend Models | 3 | 4 hours |
| Phase 3: Stripe Service | 2 | 3.5 hours |
| Phase 4: Controllers & API | 4 | 7.5 hours |
| Phase 5: Testing & Seeding | 4 | 9.5 hours |
| Phase 6: Mobile Integration | 8 | 17 hours |
| Phase 7: E2E Testing | 3 | 9 hours |
| Phase 8: Documentation | 4 | 7 hours |
| **TOTAL** | **32 Tasks** | **60 hours** |

### **Sprint Breakdown (2-week sprint):**
- **Week 1:** Backend (Phases 1-5) - 26.5 hours
- **Week 2:** Mobile & Testing (Phases 6-8) - 33.5 hours

---

## 🎯 Success Criteria

✅ **Functional Requirements:**
- [ ] Payments processed successfully via Stripe
- [ ] Payment status tracked in negotiations
- [ ] Transactions recorded for all parties
- [ ] Driver wallet balance accurate
- [ ] Mobile payment flow seamless
- [ ] Webhook events handled correctly

✅ **Non-Functional Requirements:**
- [ ] API response time < 2 seconds
- [ ] 99.9% payment success rate
- [ ] Zero data loss in transactions
- [ ] PCI DSS compliance
- [ ] Comprehensive error handling
- [ ] Full test coverage (>80%)

---

## 🔒 Security Considerations

1. **Stripe Keys:** Store in `.env`, never commit
2. **Webhook Signature:** Always verify
3. **Payment Amounts:** Server-side validation only
4. **User Authentication:** JWT verification on all endpoints
5. **SQL Injection:** Use Eloquent ORM
6. **XSS Protection:** Sanitize all inputs
7. **HTTPS Only:** Enforce SSL for payment endpoints

---

## 🐛 Risk Mitigation

| Risk | Impact | Mitigation |
|------|--------|------------|
| Stripe API downtime | High | Implement retry logic, queue system |
| Payment fraud | High | Use Stripe Radar, implement limits |
| Database deadlocks | Medium | Optimize transactions, add indexes |
| Mobile app crashes | Medium | Comprehensive error handling |
| Webhook failures | Medium | Retry mechanism, logging |
| Data inconsistency | High | Database transactions, rollback |

---

## 📚 Resources Required

### **External Services:**
- ✅ Stripe Account (Test & Live)
- ✅ Stripe Webhook Endpoint
- ✅ SSL Certificate

### **Development Tools:**
- ✅ PHP 8.0+
- ✅ Laravel 8
- ✅ Flutter 3.32.0+
- ✅ MySQL Database
- ✅ Composer
- ✅ Postman
- ✅ Stripe CLI (for webhook testing)

### **Documentation:**
- [Stripe PHP SDK](https://stripe.com/docs/api/php)
- [Stripe Flutter Package](https://pub.dev/packages/flutter_stripe)
- [Laravel Payment Processing](https://laravel.com/docs/billing)

---

## 🎉 Next Steps

**After Approval:**
1. Review and approve this plan
2. Set up Stripe test account
3. Share existing Stripe integration code for reference
4. Begin Phase 1: Database Schema
5. Daily progress updates

**Questions to Answer:**
1. What percentage should the service fee be?
2. Should we implement instant payouts for drivers?
3. Do we need refund functionality?
4. Should customers save payment methods?
5. Any specific compliance requirements (Canadian regulations)?

---

**Document Version:** 1.0  
**Last Updated:** December 14, 2025  
**Status:** 🟡 Awaiting Review & Approval  
**Prepared By:** AI Development Assistant

---

## ✅ Approval Section

- [ ] **Reviewed by:** ___________________
- [ ] **Approved:** Yes / No
- [ ] **Modifications Needed:** ___________________
- [ ] **Start Date:** ___________________
- [ ] **Target Completion:** ___________________

---

*This document will be updated as implementation progresses.*
