# Stripe Payment Integration Guide

## Overview
Complete Stripe Payment Intents integration for the Negoride ride-sharing platform with wallet management, transaction tracking, and webhook handling.

## Architecture

### Database Schema
- **payments** - Stripe payment records with Payment Intent IDs
- **transactions** - Complete financial transaction history
- **user_wallets** - Normalized wallet data (separate from admin_users to avoid row size limits)
- **negotiations** - Updated with payment status tracking

### Models
- **Payment** - Core payment business logic with Stripe integration
- **Transaction** - Financial transaction tracking with 6 categories
- **UserWallet** - Wallet balance and earnings management
- **Negotiation** - Ride negotiations with payment status

## Configuration

### 1. Environment Variables

Update `.env` with your Stripe API keys:

```bash
# Development (Test Mode) - RECOMMENDED
STRIPE_SECRET_KEY=sk_test_YOUR_TEST_SECRET_KEY
STRIPE_PUBLISHABLE_KEY=pk_test_YOUR_TEST_PUBLISHABLE_KEY
STRIPE_WEBHOOK_SECRET=whsec_YOUR_WEBHOOK_SECRET

# Production (Live Mode) - Use with caution
# STRIPE_SECRET_KEY=sk_live_YOUR_LIVE_SECRET_KEY
# STRIPE_PUBLISHABLE_KEY=pk_live_YOUR_LIVE_PUBLISHABLE_KEY
```

**Get your test keys:**
1. Go to https://dashboard.stripe.com/test/apikeys
2. Copy your **Publishable key** (starts with `pk_test_`)
3. Reveal and copy your **Secret key** (starts with `sk_test_`)

### 2. Database Setup

Run migrations to create payment tables:

```bash
php artisan migrate
```

Populate wallets for existing users:

```bash
php artisan db:seed --class=UserWalletSeeder
```

### 3. Test Data (Optional)

Seed realistic test payment data:

```bash
php artisan db:seed --class=PaymentTestSeeder
```

This creates:
- 3 completed successful payments
- 2 pending payments
- 2 failed payments
- 1 processing payment

## API Endpoints

### 1. Initiate Payment
**POST** `/api/initiate-payment`

Creates a Stripe Payment Intent and payment record.

**Request:**
```json
{
  "negotiation_id": 123,
  "amount": 75.00
}
```

**Response:**
```json
{
  "success": true,
  "message": "Payment initiated successfully",
  "data": {
    "payment_intent_id": "pi_xxx",
    "client_secret": "pi_xxx_secret_yyy",
    "payment_id": 456
  }
}
```

### 2. Verify Payment
**POST** `/api/verify-payment`

Checks payment status with Stripe.

**Request:**
```json
{
  "payment_intent_id": "pi_xxx"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "status": "succeeded",
    "payment": { /* payment details */ }
  }
}
```

### 3. Payment History
**GET** `/api/payment-history`

Retrieves user's payment history (requires authentication).

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "amount": "75.00",
      "status": "succeeded",
      "created_at": "2025-12-14T10:30:00.000000Z",
      "negotiation": { /* details */ }
    }
  ]
}
```

### 4. Payment Details
**GET** `/api/payment/{id}`

Get specific payment details (requires authentication).

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "amount": "75.00",
    "service_fee": "7.50",
    "driver_amount": "67.50",
    "status": "succeeded",
    "description": "Payment for ride service",
    "customer": { /* user details */ },
    "driver": { /* user details */ },
    "transactions": [ /* transaction list */ ]
  }
}
```

### 5. Refund Payment
**POST** `/api/refund-payment`

Process a refund for a succeeded payment.

**Request:**
```json
{
  "payment_id": 1,
  "amount": 75.00,
  "reason": "Customer requested refund"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Payment refunded successfully",
  "data": {
    "refund_id": "re_xxx",
    "amount": "75.00"
  }
}
```

### 6. Cancel Payment
**POST** `/api/cancel-payment`

Cancel a pending payment.

**Request:**
```json
{
  "payment_id": 1
}
```

**Response:**
```json
{
  "success": true,
  "message": "Payment cancelled successfully"
}
```

## Webhook Integration

### Setup Webhooks

1. **Development (Local Testing):**

Install Stripe CLI:
```bash
brew install stripe/stripe-cli/stripe
```

Login to Stripe:
```bash
stripe login
```

Forward webhooks to local server:
```bash
stripe listen --forward-to http://localhost:8888/api/webhooks/stripe
```

Copy the webhook signing secret (starts with `whsec_`) to `.env`:
```bash
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

2. **Production:**

Add webhook endpoint in Stripe Dashboard:
- Go to https://dashboard.stripe.com/webhooks
- Click "Add endpoint"
- URL: `https://yourdomain.com/api/webhooks/stripe`
- Select events:
  - `payment_intent.succeeded`
  - `payment_intent.payment_failed`
  - `payment_intent.canceled`
  - `payment_intent.requires_action`
  - `payment_intent.processing`
  - `charge.refunded`

### Supported Webhook Events

| Event | Handler | Description |
|-------|---------|-------------|
| `payment_intent.succeeded` | `handlePaymentSucceeded()` | Payment completed successfully |
| `payment_intent.payment_failed` | `handlePaymentFailed()` | Payment failed |
| `payment_intent.canceled` | `handlePaymentCanceled()` | Payment was canceled |
| `payment_intent.requires_action` | `handleRequiresAction()` | Additional authentication needed |
| `payment_intent.processing` | `handleProcessing()` | Payment is processing |
| `charge.refunded` | `handleRefunded()` | Refund was processed |

## Testing

### Run Unit Tests

```bash
php artisan test --filter=PaymentTest
```

Tests 10 scenarios:
- Payment creation with defaults
- Currency assignment
- Mark as paid/failed
- Transaction creation
- Wallet balance updates
- Relationship integrity
- Scope filters

### Run Feature Tests

```bash
php artisan test --filter=PaymentApiTest
```

Tests 13 API scenarios:
- Payment initiation
- Validation errors
- Payment verification
- Payment history (with auth)
- Payment details
- Refund processing
- Payment cancellation
- Authorization checks
- User-specific filtering

### Test with Stripe CLI

**Test successful payment:**
```bash
stripe trigger payment_intent.succeeded
```

**Test failed payment:**
```bash
stripe trigger payment_intent.payment_failed
```

**Test refund:**
```bash
stripe trigger charge.refunded
```

## Payment Flow

### Customer Makes Payment

1. **Mobile App** → Create negotiation with agreed price
2. **API** → `POST /api/initiate-payment` with negotiation_id and amount
3. **Backend** → Creates Payment Intent with Stripe
4. **Backend** → Returns client_secret to mobile app
5. **Mobile App** → Shows Stripe payment sheet with client_secret
6. **Customer** → Completes payment (card details, 3D Secure if needed)
7. **Stripe** → Sends `payment_intent.succeeded` webhook
8. **Backend Webhook** → Marks payment as paid, creates transactions, updates wallets
9. **Mobile App** → Shows success confirmation

### Transaction Creation (Automatic)

When payment succeeds, 3 transactions are created:

1. **Customer Payment** (Debit)
   - Amount: Full payment (e.g., $75.00)
   - Category: `ride_payment`

2. **Driver Earning** (Credit)
   - Amount: Payment minus service fee (e.g., $67.50)
   - Category: `ride_earning`

3. **Service Fee** (Debit)
   - Amount: Platform commission (e.g., $7.50 at 10%)
   - Category: `service_fee`

### Wallet Updates (Automatic)

Driver's wallet is updated:
- `wallet_balance` += driver_amount
- `total_earnings` += driver_amount

## Service Fee Configuration

Default: 10% commission

To change, update in `Payment` model or make configurable:

```php
// In Payment model
$serviceFee = $amount * 0.10; // 10%
```

## Security

### Webhook Signature Verification

All webhooks verify Stripe signatures:

```php
$signature = $request->header('Stripe-Signature');
\Stripe\Webhook::constructEvent($payload, $signature, $webhookSecret);
```

**Never process webhooks without verification!**

### API Authentication

Payment history and details require authentication:
- Use JWT tokens from login
- Add `Authorization: Bearer {token}` header

## Currency

Currently configured for **CAD (Canadian Dollars)**.

To change currency, update:
1. `Payment` model default currency
2. Mobile app currency configuration
3. Stripe account currency settings

## Troubleshooting

### "No such payment_intent"
- Check if you're using test keys with live payment IDs (or vice versa)
- Verify the payment_intent_id is correct

### "Webhook signature verification failed"
- Ensure `STRIPE_WEBHOOK_SECRET` is set correctly
- In development, use the secret from `stripe listen` output
- In production, use the secret from Stripe Dashboard webhook settings

### "Payment already succeeded"
- Cannot refund/cancel a payment that's already completed
- Check payment status before attempting operations

### Database Connection Issues
- Verify MySQL socket path in `.env`: `DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock`
- Check MySQL is running

## Next Steps

### 1. Get Stripe Test Keys
Visit https://dashboard.stripe.com/test/apikeys and update `.env`

### 2. Run Tests
```bash
php artisan test
```

### 3. Test with Stripe CLI
```bash
stripe listen --forward-to http://localhost:8888/api/webhooks/stripe
```

### 4. Integrate Mobile App
- Add `flutter_stripe` package to Flutter app
- Implement payment UI with Stripe payment sheet
- Handle payment confirmation
- Display transaction history and wallet balance

### 5. Production Deployment
- Switch to live Stripe keys
- Configure production webhook endpoint
- Enable HTTPS for webhook security
- Monitor payment logs

## Support

- Stripe Documentation: https://stripe.com/docs
- Stripe Payment Intents: https://stripe.com/docs/payments/payment-intents
- Stripe Webhooks: https://stripe.com/docs/webhooks
- Stripe CLI: https://stripe.com/docs/stripe-cli

## License

Proprietary - Negoride Canada
