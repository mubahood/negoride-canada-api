<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Customer;
use Stripe\Refund;
use Stripe\Exception\ApiErrorException;
use App\Models\Payment;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Log;

class StripeService
{
    protected $secretKey;
    protected $currency;
    protected $serviceFeePercentage;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret');
        $this->currency = config('services.stripe.currency', 'cad');
        $this->serviceFeePercentage = config('services.stripe.service_fee_percentage', 10);
        
        Stripe::setApiKey($this->secretKey);
        Stripe::setApiVersion(config('services.stripe.api_version', '2023-10-16'));
    }

    /**
     * Create or retrieve Stripe customer for user
     */
    public function getOrCreateCustomer(AdminUser $user): ?Customer
    {
        try {
            // If user already has a Stripe customer ID, retrieve it
            if ($user->stripe_customer_id) {
                return Customer::retrieve($user->stripe_customer_id);
            }

            // Create new Stripe customer
            $customer = Customer::create([
                'email' => $user->email,
                'name' => $user->name,
                'metadata' => [
                    'user_id' => $user->id,
                    'user_type' => 'rider', // or 'driver' based on context
                ]
            ]);

            // Save customer ID to user
            $user->stripe_customer_id = $customer->id;
            $user->save();

            return $customer;
        } catch (ApiErrorException $e) {
            Log::error('Stripe Customer Creation Failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Create a Payment Intent for ride payment
     */
    public function createPaymentIntent(
        float $amount,
        AdminUser $customer,
        AdminUser $driver,
        int $negotiationId,
        array $metadata = []
    ): ?PaymentIntent {
        try {
            // Get or create Stripe customer
            $stripeCustomer = $this->getOrCreateCustomer($customer);
            
            if (!$stripeCustomer) {
                throw new \Exception('Failed to create Stripe customer');
            }

            // Calculate amounts
            $serviceFee = round($amount * ($this->serviceFeePercentage / 100), 2);
            $driverAmount = round($amount - $serviceFee, 2);

            // Convert to cents (Stripe uses smallest currency unit)
            $amountInCents = (int) round($amount * 100);

            // Create Payment Intent
            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => $this->currency,
                'customer' => $stripeCustomer->id,
                'payment_method_types' => ['card'],
                'capture_method' => 'automatic',
                'metadata' => array_merge([
                    'negotiation_id' => $negotiationId,
                    'customer_id' => $customer->id,
                    'driver_id' => $driver->id,
                    'service_fee' => $serviceFee,
                    'driver_amount' => $driverAmount,
                ], $metadata),
                'description' => "Ride payment for negotiation #{$negotiationId}",
            ]);

            // Create Payment record in database
            Payment::create([
                'negotiation_id' => $negotiationId,
                'customer_id' => $customer->id,
                'driver_id' => $driver->id,
                'stripe_payment_intent_id' => $paymentIntent->id,
                'stripe_customer_id' => $stripeCustomer->id,
                'amount' => $amount,
                'service_fee' => $serviceFee,
                'driver_amount' => $driverAmount,
                'currency' => $this->currency,
                'status' => 'pending',
                'payment_type' => 'ride_payment',
                'description' => "Ride payment for negotiation #{$negotiationId}",
                'metadata' => $metadata,
            ]);

            return $paymentIntent;
        } catch (ApiErrorException $e) {
            Log::error('Stripe Payment Intent Creation Failed', [
                'amount' => $amount,
                'customer_id' => $customer->id,
                'driver_id' => $driver->id,
                'negotiation_id' => $negotiationId,
                'error' => $e->getMessage()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Payment Intent Creation Error', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Confirm a Payment Intent
     */
    public function confirmPaymentIntent(string $paymentIntentId, ?string $paymentMethodId = null): ?PaymentIntent
    {
        try {
            $params = [];
            
            if ($paymentMethodId) {
                $params['payment_method'] = $paymentMethodId;
            }

            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            
            if ($paymentIntent->status === 'requires_payment_method' || 
                $paymentIntent->status === 'requires_confirmation') {
                $paymentIntent = $paymentIntent->confirm($params);
            }

            return $paymentIntent;
        } catch (ApiErrorException $e) {
            Log::error('Stripe Payment Intent Confirmation Failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Retrieve a Payment Intent
     */
    public function retrievePaymentIntent(string $paymentIntentId): ?PaymentIntent
    {
        try {
            return PaymentIntent::retrieve($paymentIntentId);
        } catch (ApiErrorException $e) {
            Log::error('Stripe Payment Intent Retrieval Failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Cancel a Payment Intent
     */
    public function cancelPaymentIntent(string $paymentIntentId): ?PaymentIntent
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            
            if (in_array($paymentIntent->status, ['requires_payment_method', 'requires_confirmation', 'requires_action'])) {
                return $paymentIntent->cancel();
            }

            return $paymentIntent;
        } catch (ApiErrorException $e) {
            Log::error('Stripe Payment Intent Cancellation Failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Create a refund
     */
    public function createRefund(string $paymentIntentId, ?float $amount = null, string $reason = 'requested_by_customer'): ?Refund
    {
        try {
            $params = [
                'payment_intent' => $paymentIntentId,
                'reason' => $reason,
            ];

            // If partial refund amount specified
            if ($amount !== null) {
                $params['amount'] = (int) round($amount * 100); // Convert to cents
            }

            $refund = Refund::create($params);

            // Update Payment record in database
            $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();
            if ($payment) {
                $payment->status = 'refunded';
                $payment->refunded_at = now();
                $payment->save();

                // Reverse wallet balances
                $payment->reverseWalletBalances();
            }

            return $refund;
        } catch (ApiErrorException $e) {
            Log::error('Stripe Refund Creation Failed', [
                'payment_intent_id' => $paymentIntentId,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Handle successful payment webhook
     */
    public function handlePaymentSuccess(PaymentIntent $paymentIntent): bool
    {
        try {
            $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();

            if (!$payment) {
                Log::error('Payment record not found for successful payment intent', [
                    'payment_intent_id' => $paymentIntent->id
                ]);
                return false;
            }

            // Update payment status
            $payment->status = 'succeeded';
            $payment->stripe_payment_method = $paymentIntent->payment_method ?? null;
            $payment->paid_at = now();
            $payment->save();

            // Mark payment as paid and create transactions
            $payment->markAsPaid();

            // Update negotiation
            $negotiation = $payment->negotiation;
            if ($negotiation) {
                $negotiation->payment_status = 'paid';
                $negotiation->payment_completed_at = now();
                $negotiation->save();
            }

            Log::info('Payment processed successfully', [
                'payment_id' => $payment->id,
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $payment->amount
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Payment success handling failed', [
                'payment_intent_id' => $paymentIntent->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Handle failed payment webhook
     */
    public function handlePaymentFailed(PaymentIntent $paymentIntent): bool
    {
        try {
            $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();

            if (!$payment) {
                Log::error('Payment record not found for failed payment intent', [
                    'payment_intent_id' => $paymentIntent->id
                ]);
                return false;
            }

            // Update payment status
            $payment->status = 'failed';
            $payment->failed_at = now();
            $payment->metadata = array_merge($payment->metadata ?? [], [
                'failure_code' => $paymentIntent->last_payment_error->code ?? null,
                'failure_message' => $paymentIntent->last_payment_error->message ?? null,
            ]);
            $payment->save();

            // Mark payment as failed
            $payment->markAsFailed();

            // Update negotiation
            $negotiation = $payment->negotiation;
            if ($negotiation) {
                $negotiation->payment_status = 'failed';
                $negotiation->save();
            }

            Log::info('Payment marked as failed', [
                'payment_id' => $payment->id,
                'payment_intent_id' => $paymentIntent->id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Payment failure handling failed', [
                'payment_intent_id' => $paymentIntent->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Calculate service fee
     */
    public function calculateServiceFee(float $amount): float
    {
        return round($amount * ($this->serviceFeePercentage / 100), 2);
    }

    /**
     * Calculate driver amount after service fee
     */
    public function calculateDriverAmount(float $amount): float
    {
        $serviceFee = $this->calculateServiceFee($amount);
        return round($amount - $serviceFee, 2);
    }
}
