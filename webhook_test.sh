#!/bin/bash

# Webhook Testing Script for Stripe Integration
# This script triggers all 6 webhook events sequentially

echo "=================================="
echo "Stripe Webhook Testing Script"
echo "=================================="
echo ""

# Check if Stripe CLI is installed
if ! command -v stripe &> /dev/null; then
    echo "❌ Error: Stripe CLI is not installed"
    echo "Install with: brew install stripe/stripe-cli/stripe"
    exit 1
fi

# Check if stripe listen is running
if ! curl -s http://localhost:8888/api/webhooks/stripe &> /dev/null; then
    echo "⚠️  Warning: Make sure 'stripe listen' is running in another terminal"
    echo "Run: stripe listen --forward-to http://localhost:8888/api/webhooks/stripe"
    echo ""
    read -p "Press Enter to continue or Ctrl+C to cancel..."
fi

echo "Starting webhook tests..."
echo ""

# Test 1: Payment Succeeded
echo "📝 Test 1/6: Testing payment_intent.succeeded..."
stripe trigger payment_intent.succeeded
if [ $? -eq 0 ]; then
    echo "✅ Payment succeeded webhook sent"
else
    echo "❌ Failed to send payment succeeded webhook"
fi
sleep 3

# Test 2: Payment Failed
echo ""
echo "📝 Test 2/6: Testing payment_intent.payment_failed..."
stripe trigger payment_intent.payment_failed
if [ $? -eq 0 ]; then
    echo "✅ Payment failed webhook sent"
else
    echo "❌ Failed to send payment failed webhook"
fi
sleep 3

# Test 3: Payment Canceled
echo ""
echo "📝 Test 3/6: Testing payment_intent.canceled..."
stripe trigger payment_intent.canceled
if [ $? -eq 0 ]; then
    echo "✅ Payment canceled webhook sent"
else
    echo "❌ Failed to send payment canceled webhook"
fi
sleep 3

# Test 4: Payment Processing
echo ""
echo "📝 Test 4/6: Testing payment_intent.processing..."
stripe trigger payment_intent.processing
if [ $? -eq 0 ]; then
    echo "✅ Payment processing webhook sent"
else
    echo "❌ Failed to send payment processing webhook"
fi
sleep 3

# Test 5: Payment Requires Action
echo ""
echo "📝 Test 5/6: Testing payment_intent.requires_action..."
stripe trigger payment_intent.requires_action
if [ $? -eq 0 ]; then
    echo "✅ Payment requires action webhook sent"
else
    echo "❌ Failed to send payment requires action webhook"
fi
sleep 3

# Test 6: Charge Refunded
echo ""
echo "📝 Test 6/6: Testing charge.refunded..."
stripe trigger charge.refunded
if [ $? -eq 0 ]; then
    echo "✅ Charge refunded webhook sent"
else
    echo "❌ Failed to send charge refunded webhook"
fi

echo ""
echo "=================================="
echo "All webhook tests completed! ✅"
echo "=================================="
echo ""
echo "Next steps:"
echo "1. Check storage/logs/laravel.log for webhook processing logs"
echo "2. Verify payments table: SELECT * FROM payments ORDER BY id DESC LIMIT 6;"
echo "3. Verify transactions table: SELECT * FROM transactions ORDER BY id DESC;"
echo "4. Verify user_wallets updates: SELECT * FROM user_wallets WHERE updated_at > NOW() - INTERVAL 5 MINUTE;"
echo ""
echo "For detailed verification, see WEBHOOK_TESTING_GUIDE.md"
