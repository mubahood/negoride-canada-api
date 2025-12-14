# Stripe Webhook Testing Guide

## Prerequisites

### 1. Update Command Line Tools (Required for Stripe CLI)

```bash
# Check current version
xcode-select --version

# Update Command Line Tools
sudo rm -rf /Library/Developer/CommandLineTools
sudo xcode-select --install

# Or download manually from:
# https://developer.apple.com/download/all/
# (Download Command Line Tools for Xcode 16.0)
```

### 2. Install Stripe CLI

After updating Command Line Tools:

```bash
brew install stripe/stripe-cli/stripe
```

Verify installation:

```bash
stripe --version
```

## Setup Steps

### 1. Login to Stripe

```bash
stripe login
```

This will:
- Open your browser to authorize the CLI
- Link CLI to your Stripe account
- Store credentials in `~/.config/stripe/config.toml`

### 2. Start Local Server

Ensure your Laravel API is running on port 8888:

```bash
# In terminal 1
cd /Applications/MAMP/htdocs/negoride-canada-api
php artisan serve --port=8888
```

Or use MAMP with localhost:8888 configured.

### 3. Forward Webhooks to Local Server

```bash
# In terminal 2
stripe listen --forward-to http://localhost:8888/api/webhooks/stripe
```

**Important:** Copy the webhook signing secret from the output:
```
> Ready! Your webhook signing secret is whsec_xxxxxxxxxxxxx
```

Update `.env` with this secret:
```bash
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxx
```

Clear config cache:
```bash
php artisan config:clear
```

## Testing Individual Webhook Events

Keep `stripe listen` running in terminal 2. In terminal 3, trigger test events:

### 1. Payment Succeeded

```bash
stripe trigger payment_intent.succeeded
```

**Expected Behavior:**
- Webhook received at `/api/webhooks/stripe`
- `handlePaymentSucceeded()` method called
- Payment status updated to `succeeded`
- 3 transactions created:
  - Customer payment (debit)
  - Driver earning (credit)
  - Service fee (debit)
- Driver wallet balance updated
- Negotiation status updated to `paid`

**Check in database:**
```sql
SELECT * FROM payments WHERE status = 'succeeded' ORDER BY id DESC LIMIT 1;
SELECT * FROM transactions WHERE payment_id = [last_payment_id];
SELECT * FROM user_wallets WHERE user_id = [driver_id];
SELECT * FROM negotiations WHERE id = [negotiation_id];
```

### 2. Payment Failed

```bash
stripe trigger payment_intent.payment_failed
```

**Expected Behavior:**
- Payment status updated to `failed`
- `failure_reason` stored in database
- No transactions created
- Wallet not updated

**Check in database:**
```sql
SELECT * FROM payments WHERE status = 'failed' ORDER BY id DESC LIMIT 1;
```

### 3. Payment Canceled

```bash
stripe trigger payment_intent.canceled
```

**Expected Behavior:**
- Payment status updated to `canceled`
- No transactions created
- Wallet not updated

**Check in database:**
```sql
SELECT * FROM payments WHERE status = 'canceled' ORDER BY id DESC LIMIT 1;
```

### 4. Payment Processing

```bash
stripe trigger payment_intent.processing
```

**Expected Behavior:**
- Payment status updated to `processing`
- Waiting for final confirmation

**Check in database:**
```sql
SELECT * FROM payments WHERE status = 'processing' ORDER BY id DESC LIMIT 1;
```

### 5. Payment Requires Action

```bash
stripe trigger payment_intent.requires_action
```

**Expected Behavior:**
- Payment status updated to `requires_action`
- Customer needs to complete 3D Secure or other authentication

**Check in database:**
```sql
SELECT * FROM payments WHERE status = 'requires_action' ORDER BY id DESC LIMIT 1;
```

### 6. Charge Refunded

```bash
stripe trigger charge.refunded
```

**Expected Behavior:**
- Payment status updated to `refunded`
- Refund transactions created (reversing original transactions)
- Driver wallet balance decreased

**Check in database:**
```sql
SELECT * FROM payments WHERE status = 'refunded' ORDER BY id DESC LIMIT 1;
SELECT * FROM transactions WHERE category = 'refund' ORDER BY id DESC;
SELECT * FROM user_wallets WHERE user_id = [driver_id];
```

## Monitoring Webhooks

### View Webhook Logs in Stripe CLI

The `stripe listen` command shows real-time webhook events:

```
2025-12-15 10:30:15   --> payment_intent.succeeded [evt_xxx]
2025-12-15 10:30:15  <--  [200] POST http://localhost:8888/api/webhooks/stripe [evt_xxx]
```

### Check Laravel Logs

```bash
tail -f storage/logs/laravel.log
```

Look for:
- Webhook received logs
- Payment status updates
- Transaction creation logs
- Wallet update logs
- Any error messages

### View Stripe Dashboard

1. Go to https://dashboard.stripe.com/test/webhooks
2. Click on individual webhook events
3. View request/response details
4. Check for failures or retries

## Testing with Real Payment Flow

### Create Test Payment Intent

```bash
# In terminal 3
stripe payment_intents create \
  --amount=7500 \
  --currency=cad \
  --description="Test ride payment" \
  --metadata[customer_id]=123 \
  --metadata[driver_id]=456 \
  --metadata[negotiation_id]=789
```

This returns a Payment Intent ID like `pi_xxxxxxxxxxxxx`.

### Store Payment in Database

Use your API:

```bash
curl -X POST http://localhost:8888/api/initiate-payment \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{
    "negotiation_id": 789,
    "amount": 75.00
  }'
```

### Confirm Payment (Simulate Success)

```bash
stripe payment_intents confirm pi_xxxxxxxxxxxxx \
  --payment-method=pm_card_visa
```

This triggers `payment_intent.succeeded` webhook automatically.

### Verify Full Flow

```bash
# Check payment status
curl http://localhost:8888/api/payment/1 \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"

# Check payment history
curl http://localhost:8888/api/payment-history \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## Common Issues & Solutions

### Issue: "Webhook signature verification failed"

**Cause:** Incorrect webhook secret in `.env`

**Solution:**
1. Copy the secret from `stripe listen` output
2. Update `STRIPE_WEBHOOK_SECRET` in `.env`
3. Run `php artisan config:clear`

### Issue: "No such payment_intent"

**Cause:** Using test Payment Intent ID with live API keys (or vice versa)

**Solution:**
- Ensure `STRIPE_SECRET_KEY` starts with `sk_test_`
- Ensure `stripe listen` is using test mode

### Issue: Webhook not reaching server

**Cause:** Laravel server not running on port 8888

**Solution:**
```bash
# Check if server is running
lsof -i :8888

# Restart server
php artisan serve --port=8888
```

### Issue: "Route not found" for webhook

**Cause:** Webhook route not registered

**Solution:**
Check `routes/api.php` has:
```php
Route::post('/webhooks/stripe', [ApiWebhookController::class, 'handleWebhook']);
```

## Automated Testing Script

Create a test script to automate all webhook tests:

```bash
#!/bin/bash
# webhook_test.sh

echo "Starting webhook tests..."

echo "\n1. Testing payment_intent.succeeded..."
stripe trigger payment_intent.succeeded
sleep 2

echo "\n2. Testing payment_intent.payment_failed..."
stripe trigger payment_intent.payment_failed
sleep 2

echo "\n3. Testing payment_intent.canceled..."
stripe trigger payment_intent.canceled
sleep 2

echo "\n4. Testing payment_intent.processing..."
stripe trigger payment_intent.processing
sleep 2

echo "\n5. Testing payment_intent.requires_action..."
stripe trigger payment_intent.requires_action
sleep 2

echo "\n6. Testing charge.refunded..."
stripe trigger charge.refunded
sleep 2

echo "\nAll webhook tests completed!"
echo "Check storage/logs/laravel.log for details"
```

Make executable and run:

```bash
chmod +x webhook_test.sh
./webhook_test.sh
```

## Verification Checklist

After running all tests, verify:

- [ ] All 6 webhook events triggered successfully
- [ ] Payment statuses updated correctly in database
- [ ] Transactions created for succeeded payments
- [ ] Wallet balances updated for succeeded payments
- [ ] Refund transactions created for refunded payments
- [ ] No PHP errors in Laravel logs
- [ ] All webhook events show 200 response in Stripe CLI
- [ ] Stripe Dashboard shows all events processed

## Production Webhook Setup

When ready for production:

1. **Create Production Webhook Endpoint:**
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

2. **Update Production Environment:**
   - Copy the webhook signing secret
   - Update `.env` on production server
   - Run `php artisan config:clear`

3. **Enable HTTPS:**
   - Stripe requires HTTPS for production webhooks
   - Ensure SSL certificate is valid

4. **Monitor Webhook Health:**
   - Check Stripe Dashboard → Developers → Webhooks
   - View success/failure rates
   - Set up alerts for failed webhooks

## Next Steps

1. ✅ Update Command Line Tools for Xcode 16.0
2. ✅ Install Stripe CLI
3. ✅ Test all 6 webhook events
4. ✅ Verify database updates
5. ✅ Check Laravel logs
6. ✅ Test with real payment flow
7. ✅ Document any issues found
8. ⏭️ Integrate with Flutter mobile app
9. ⏭️ Configure production webhooks
10. ⏭️ Deploy to production

## Support

- Stripe CLI Docs: https://stripe.com/docs/stripe-cli
- Webhook Testing: https://stripe.com/docs/webhooks/test
- Stripe Events: https://stripe.com/docs/api/events/types
