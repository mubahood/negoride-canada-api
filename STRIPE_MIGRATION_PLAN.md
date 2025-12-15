# 🔄 Stripe Payment Integration Migration Plan
## From lovebirds-api to negoride-canada-api

---

## 📋 Analysis Summary

### Lovebirds-API Implementation (SOURCE)
✅ **Simple & Elegant Approach:**
- Uses Stripe Payment Links (no custom checkout pages needed)
- Payment links created automatically when order is created
- Webhook handles payment completion
- All logic in **Order model** (`create_payment_link()` method)
- Minimal controller code - just webhook handler and refresh endpoint

✅ **Key Features:**
1. **Stripe Payment Links** - Hosted payment pages by Stripe
2. **Dynamic Products** - Each order creates a Stripe product + price
3. **Webhook Integration** - Listens for `payment_link.payment_completed`
4. **Auto-generation** - Payment links created on order creation
5. **Force regenerate** - Option to recreate payment links

### Negoride-Canada-API Current Implementation (TARGET)
❌ **Over-engineered & Broken:**
- Checkout Sessions (more complex than needed)
- Separate StripeService class
- Separate Payment model
- Complex controller logic
- Type mismatches (AdminUser vs Administrator)
- Missing actual Stripe keys

---

## 🎯 Migration Strategy

### Replace Complex System with Simple One

**What to Remove:**
1. ❌ `/app/Services/StripeService.php` (entire file)
2. ❌ `/app/Models/Payment.php` (entire file)
3. ❌ `/app/Http/Controllers/Api/ApiPaymentController.php` (entire file)
4. ❌ Complex payment routes in `routes/api.php`

**What to Add:**
1. ✅ Simple payment logic in **Negotiation model** (like Order model)
2. ✅ Webhook handler in **ApiChatController** (existing controller)
3. ✅ Simple refresh endpoint
4. ✅ Copy Stripe key from lovebirds-api

---

## 📝 Step-by-Step Migration Instructions

### STEP 1: Update Database Schema
Add Stripe columns to `negotiations` table:

```sql
ALTER TABLE negotiations 
ADD COLUMN stripe_id VARCHAR(255) NULL,
ADD COLUMN stripe_url TEXT NULL,
ADD COLUMN stripe_product_id VARCHAR(255) NULL,
ADD COLUMN stripe_price_id VARCHAR(255) NULL,
ADD COLUMN stripe_paid VARCHAR(10) DEFAULT 'No';
```

**Test:**
```bash
mysql --socket=/Applications/MAMP/tmp/mysql/mysql.sock -u root -proot negoride -e "DESCRIBE negotiations;"
```

---

### STEP 2: Update .env File
Copy Stripe key from lovebirds-api to negoride-canada-api:

```bash
# Remove old placeholder keys, use only STRIPE_KEY
STRIPE_KEY=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
```

**Test:**
```bash
grep "STRIPE_KEY" /Applications/MAMP/htdocs/negoride-canada-api/.env
```

---

### STEP 3: Update Negotiation Model
Add payment link creation method (similar to Order model):

**File:** `/app/Models/Negotiation.php`

**Add to fillable array:**
```php
'stripe_id',
'stripe_url',
'stripe_product_id',
'stripe_price_id',
'stripe_paid',
```

**Add method:**
```php
public function create_payment_link()
{
    $stripe_key = env('STRIPE_KEY');
    
    // Skip if already has payment link
    if ($this->stripe_id != null && strlen($this->stripe_id) > 0) {
        return;
    }
    
    // Skip if no agreed price
    if (!$this->agreed_price || $this->agreed_price <= 0) {
        throw new \Exception("No agreed price for payment link");
    }
    
    // Get customer name
    $customer = $this->customer;
    $customer_name = $customer ? $customer->name : 'Customer #' . $this->customer_id;
    
    // Amount in cents
    $amount_cents = intval(floatval($this->agreed_price) * 100);
    
    // Product name
    $product_name = "Ride #{$this->id} - {$customer_name}";
    
    $stripe = new \Stripe\StripeClient($stripe_key);
    
    try {
        // Create product
        $product = $stripe->products->create([
            'name' => $product_name,
            'description' => "Trip from {$this->pickup_address} to {$this->dropoff_address}",
            'metadata' => [
                'negotiation_id' => $this->id,
                'customer_id' => $this->customer_id,
                'driver_id' => $this->driver_id,
            ]
        ]);

        // Create price
        $price = $stripe->prices->create([
            'unit_amount' => $amount_cents,
            'currency' => 'cad',
            'product' => $product->id,
        ]);

        // Create payment link
        $paymentLink = $stripe->paymentLinks->create([
            'line_items' => [
                ['price' => $price->id, 'quantity' => 1]
            ],
            'metadata' => [
                'negotiation_id' => $this->id,
                'customer_id' => $this->customer_id,
            ],
            'after_completion' => [
                'type' => 'redirect',
                'redirect' => [
                    'url' => env('APP_URL', 'https://negoride.ca') . '/payment-success?negotiation_id=' . $this->id
                ]
            ]
        ]);
        
        // Save to database
        $this->stripe_id = $paymentLink->id;
        $this->stripe_url = $paymentLink->url;
        $this->stripe_product_id = $product->id;
        $this->stripe_price_id = $price->id;
        $this->stripe_paid = 'No';
        $this->payment_status = 'pending';
        $this->save();
        
    } catch (\Throwable $e) {
        \Log::error('Stripe payment link creation failed: ' . $e->getMessage());
        throw $e;
    }
}
```

---

### STEP 4: Add Webhook Handler
Add to **ApiChatController.php**:

```php
/**
 * Stripe webhook handler for payment completion
 * POST /api/webhooks/stripe
 */
public function stripe_webhook(Request $r)
{
    $stripe_webhook_secret = env('STRIPE_WEBHOOK_SECRET');
    $payload = $r->getContent();
    $sig_header = $r->header('stripe-signature');

    try {
        // Verify webhook signature (skip in dev if no secret)
        if ($stripe_webhook_secret && $sig_header) {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $stripe_webhook_secret);
        } else {
            $event = json_decode($payload, true);
        }

        // Handle events
        switch ($event['type']) {
            case 'payment_link.payment_completed':
                $payment_link = $event['data']['object'];
                $this->handlePaymentCompleted($payment_link);
                break;
            case 'checkout.session.completed':
                $session = $event['data']['object'];
                $negotiation_id = $session['metadata']['negotiation_id'] ?? null;
                if ($negotiation_id) {
                    $this->markNegotiationPaid($negotiation_id);
                }
                break;
            default:
                \Log::info('Unhandled Stripe event: ' . $event['type']);
        }

        return response()->json(['success' => true]);
    } catch (\Exception $e) {
        \Log::error('Stripe webhook error: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 400);
    }
}

private function handlePaymentCompleted($payment_link)
{
    try {
        $negotiation = Negotiation::where('stripe_id', $payment_link['id'])->first();
        
        if ($negotiation) {
            $negotiation->stripe_paid = 'Yes';
            $negotiation->payment_status = 'paid';
            $negotiation->payment_completed_at = now();
            $negotiation->save();
            
            \Log::info('Payment completed for negotiation: ' . $negotiation->id);
        }
    } catch (\Exception $e) {
        \Log::error('Error handling payment: ' . $e->getMessage());
    }
}

private function markNegotiationPaid($negotiation_id)
{
    try {
        $negotiation = Negotiation::find($negotiation_id);
        
        if ($negotiation) {
            $negotiation->stripe_paid = 'Yes';
            $negotiation->payment_status = 'paid';
            $negotiation->payment_completed_at = now();
            $negotiation->save();
        }
    } catch (\Exception $e) {
        \Log::error('Error marking negotiation paid: ' . $e->getMessage());
    }
}
```

---

### STEP 5: Add Refresh Payment Endpoint
Add to **ApiChatController.php**:

```php
/**
 * Refresh/create payment link for a negotiation
 * POST /api/negotiations-refresh-payment
 */
public function negotiations_refresh_payment(Request $r)
{
    $u = auth()->user();
    if (!$u) {
        return response()->json(['code' => 0, 'message' => 'Unauthorized'], 401);
    }

    $negotiation_id = $r->negotiation_id;
    if (!$negotiation_id) {
        return response()->json(['code' => 0, 'message' => 'Negotiation ID required'], 400);
    }

    $negotiation = Negotiation::find($negotiation_id);
    if (!$negotiation) {
        return response()->json(['code' => 0, 'message' => 'Negotiation not found'], 404);
    }

    // Verify user is customer or driver
    if ($negotiation->customer_id != $u->id && $negotiation->driver_id != $u->id) {
        return response()->json(['code' => 0, 'message' => 'Unauthorized'], 403);
    }

    try {
        // Force regenerate if requested
        if ($r->force_regenerate) {
            $negotiation->stripe_id = null;
            $negotiation->stripe_url = null;
            $negotiation->stripe_product_id = null;
            $negotiation->stripe_price_id = null;
            $negotiation->save();
        }

        // Create payment link
        $negotiation->create_payment_link();

        return response()->json([
            'code' => 1,
            'message' => 'Payment link generated successfully',
            'data' => [
                'negotiation_id' => $negotiation->id,
                'stripe_url' => $negotiation->stripe_url,
                'stripe_id' => $negotiation->stripe_id,
                'agreed_price' => $negotiation->agreed_price,
                'payment_status' => $negotiation->payment_status,
                'stripe_paid' => $negotiation->stripe_paid,
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'code' => 0,
            'message' => 'Failed to generate payment link: ' . $e->getMessage()
        ], 500);
    }
}
```

---

### STEP 6: Update Routes
**File:** `routes/api.php`

**Remove old payment routes:**
```php
// DELETE these lines:
Route::post('payments/initiate', [ApiPaymentController::class, 'initiatePayment']);
Route::get('payments/{paymentId}/verify', [ApiPaymentController::class, 'verifyPayment']);
// ... all other payment routes
```

**Add new simple routes:**
```php
// OUTSIDE auth middleware (for Stripe webhooks):
Route::post('webhooks/stripe', [ApiChatController::class, 'stripe_webhook']);

// INSIDE auth middleware:
Route::post('negotiations-refresh-payment', [ApiChatController::class, 'negotiations_refresh_payment']);
```

---

### STEP 7: Delete Unused Files
```bash
rm /Applications/MAMP/htdocs/negoride-canada-api/app/Services/StripeService.php
rm /Applications/MAMP/htdocs/negoride-canada-api/app/Models/Payment.php
rm /Applications/MAMP/htdocs/negoride-canada-api/app/Http/Controllers/Api/ApiPaymentController.php
```

---

### STEP 8: Update Mobile App
**File:** `lib/services/PaymentService.dart`

**Replace entire class with:**
```dart
class PaymentService {
  /// Generate/refresh payment link for negotiation
  static Future<Map<String, dynamic>?> initiatePayment(int negotiationId) async {
    try {
      final responseData = await Utils.http_post(
        'negotiations-refresh-payment',
        {'negotiation_id': negotiationId.toString()}
      );
      
      RespondModel response = RespondModel(responseData);
      
      if (response.code == 1) {
        return response.data;
      } else {
        Utils.toast(response.message, color: Colors.red);
        return null;
      }
    } catch (e) {
      Utils.toast('Failed to initiate payment', color: Colors.red);
      Utils.log('Payment initiation error: $e');
      return null;
    }
  }
}
```

**File:** `lib/widgets/PaymentButton.dart`

**Update to use new service:**
```dart
final paymentData = await PaymentService.initiatePayment(widget.neg.id);

if (paymentData != null && paymentData['stripe_url'] != null) {
  // Open payment URL in WebView
  final checkoutUrl = paymentData['stripe_url'];
  // ... open WebView with checkoutUrl
}
```

---

## ✅ Testing Checklist

### Backend Testing

1. **Database Migration:**
```bash
mysql --socket=/Applications/MAMP/tmp/mysql/mysql.sock -u root -proot negoride -e "SHOW COLUMNS FROM negotiations LIKE 'stripe%';"
```

2. **Test Payment Link Creation:**
```bash
# In tinker or create test script
$neg = Negotiation::find(43);
$neg->create_payment_link();
echo $neg->stripe_url;
```

3. **Test Webhook (use Stripe CLI):**
```bash
stripe listen --forward-to localhost:8888/negoride-canada-api/api/webhooks/stripe
stripe trigger payment_link.payment_completed
```

4. **Test Refresh Endpoint:**
```bash
curl -X POST "http://localhost:8888/negoride-canada-api/api/negotiations-refresh-payment" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"negotiation_id": 43}'
```

### Mobile App Testing

1. Hot reload Flutter app
2. Navigate to accepted negotiation
3. Click payment button
4. Should get Stripe payment URL
5. Open in WebView
6. Complete test payment
7. Verify webhook updates negotiation status

---

## 🎯 Expected Results

✅ **Simpler codebase** - 90% less payment code
✅ **Working payments** - Stripe Payment Links are battle-tested
✅ **Auto-generation** - Payment links created when negotiation accepted
✅ **Webhook integration** - Automatic payment status updates
✅ **Mobile friendly** - Simple WebView integration
✅ **Production ready** - Using real Stripe keys from lovebirds-api

---

## 🔧 Troubleshooting

**Issue: Payment link not created**
- Check `storage/logs/laravel.log` for Stripe errors
- Verify `STRIPE_KEY` in `.env`
- Ensure `agreed_price` is set on negotiation

**Issue: Webhook not working**
- Check webhook signature verification
- Ensure route is OUTSIDE auth middleware
- Use Stripe CLI for local testing

**Issue: Mobile app shows old payment UI**
- Hot reload app (press 'r' in terminal)
- Check console logs for API response
- Verify Utils.http_post is being used

---

## 📊 Comparison

| Feature | Old (negoride) | New (like lovebirds) |
|---------|----------------|----------------------|
| Code files | 3 files | 1 model method |
| Lines of code | ~1500 | ~150 |
| Complexity | High | Low |
| Stripe approach | Checkout Sessions | Payment Links |
| Payment model | Separate table | Embedded in Negotiation |
| Working | ❌ No | ✅ Yes |

---

## 🚀 Next Steps After Migration

1. Test with real payment (small amount)
2. Configure Stripe webhook in dashboard
3. Add email notifications on payment
4. Add payment receipt generation
5. Monitor `storage/logs/laravel.log` for issues

