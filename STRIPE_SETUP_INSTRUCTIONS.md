# Stripe Payment Setup Instructions

## Problem Found
❌ Stripe API keys are not configured correctly
- Config expects: `STRIPE_SECRET` and `STRIPE_KEY`
- .env has: `STRIPE_SECRET_KEY` and `STRIPE_PUBLISHABLE_KEY`
- Current values are placeholders, not real API keys

## Solution: Get Real Stripe Test Keys

### Step 1: Get Your Stripe Test API Keys
1. Go to: https://dashboard.stripe.com/test/apikeys
2. Log in to your Stripe account (create one if needed - it's free)
3. You'll see two keys:
   - **Publishable key** (starts with `pk_test_...`)
   - **Secret key** (starts with `sk_test_...` - click "Reveal test key")

### Step 2: Update .env File
Open `/Applications/MAMP/htdocs/negoride-canada-api/.env` and replace:

```env
# REPLACE THESE LINES:
STRIPE_SECRET_KEY=sk_test_YOUR_TEST_SECRET_KEY_HERE
STRIPE_PUBLISHABLE_KEY=pk_test_YOUR_TEST_PUBLISHABLE_KEY_HERE
STRIPE_WEBHOOK_SECRET=whsec_YOUR_WEBHOOK_SECRET_HERE

# WITH YOUR ACTUAL KEYS:
STRIPE_SECRET=sk_test_YOUR_ACTUAL_SECRET_KEY_FROM_STRIPE
STRIPE_KEY=pk_test_YOUR_ACTUAL_PUBLISHABLE_KEY_FROM_STRIPE
STRIPE_WEBHOOK_SECRET=whsec_YOUR_WEBHOOK_SECRET

# NOTE: The variable names changed to match config/services.php
```

### Step 3: Test Stripe Connection
```bash
curl -X GET "http://localhost:8888/negoride-canada-api/api/payments/test-stripe" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

Expected response:
```json
{
  "success": true,
  "message": "Stripe connection successful",
  "data": {
    "stripe_connected": true,
    "test_mode": true,
    "account_id": "acct_..."
  }
}
```

### Step 4: Get Webhook Secret (For Production)
1. Go to: https://dashboard.stripe.com/test/webhooks
2. Click "Add endpoint"
3. URL: `https://your-domain.com/api/webhooks/stripe`
4. Events to listen for:
   - `checkout.session.completed`
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
5. Copy the webhook signing secret (starts with `whsec_...`)
6. Add to `.env`: `STRIPE_WEBHOOK_SECRET=whsec_YOUR_SECRET`

## Test Payment Flow

### Test Card Numbers
Use these test cards in Stripe Checkout:

| Card Number | Result |
|-------------|--------|
| 4242 4242 4242 4242 | Success |
| 4000 0000 0000 9995 | Declined |
| 4000 0025 0000 3155 | Requires authentication (3D Secure) |

- Use any future expiry date (e.g., 12/34)
- Use any 3-digit CVC
- Use any ZIP code

## Current Configuration
```php
// config/services.php
'stripe' => [
    'key' => env('STRIPE_KEY'),              // Publishable key
    'secret' => env('STRIPE_SECRET'),        // Secret key
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'currency' => 'cad',                     // Canadian Dollars
    'service_fee_percentage' => 10,          // 10% platform fee
],
```

## After Setting Up Keys
1. Restart MAMP/Apache to load new .env values
2. Test the connection using the test endpoint
3. Try initiating a payment from the mobile app
4. Check Laravel logs: `storage/logs/laravel.log`

## Logging
All payment operations now have extensive logging. Check:
```bash
tail -f storage/logs/laravel.log
```

Look for emojis:
- 🚀 Payment Initiation Started
- 📦 Negotiation Loaded
- 👥 Users Loaded
- 💳 Calling Stripe Service
- ✅ Success indicators
- ❌🔴 Error indicators

