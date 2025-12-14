<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class ApiWebhookController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Handle Stripe webhook events
     * POST /api/webhooks/stripe
     */
    public function handleStripeWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            // Verify webhook signature
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $webhookSecret
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            Log::error('Stripe Webhook: Invalid payload', [
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            // Invalid signature
            Log::error('Stripe Webhook: Invalid signature', [
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Handle the event
        try {
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event->data->object);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event->data->object);
                    break;

                case 'payment_intent.canceled':
                    $this->handlePaymentIntentCanceled($event->data->object);
                    break;

                case 'payment_intent.requires_action':
                    $this->handlePaymentIntentRequiresAction($event->data->object);
                    break;

                case 'payment_intent.processing':
                    $this->handlePaymentIntentProcessing($event->data->object);
                    break;

                case 'charge.refunded':
                    $this->handleChargeRefunded($event->data->object);
                    break;

                default:
                    // Unexpected event type
                    Log::info('Stripe Webhook: Unhandled event type', [
                        'type' => $event->type
                    ]);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Stripe Webhook: Error processing event', [
                'type' => $event->type,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Webhook handler failed'], 500);
        }
    }

    /**
     * Handle payment_intent.succeeded event
     */
    protected function handlePaymentIntentSucceeded($paymentIntent): void
    {
        Log::info('Stripe Webhook: Payment Intent Succeeded', [
            'payment_intent_id' => $paymentIntent->id,
            'amount' => $paymentIntent->amount / 100,
        ]);

        $this->stripeService->handlePaymentSuccess($paymentIntent);
    }

    /**
     * Handle payment_intent.payment_failed event
     */
    protected function handlePaymentIntentFailed($paymentIntent): void
    {
        Log::info('Stripe Webhook: Payment Intent Failed', [
            'payment_intent_id' => $paymentIntent->id,
            'failure_code' => $paymentIntent->last_payment_error->code ?? null,
            'failure_message' => $paymentIntent->last_payment_error->message ?? null,
        ]);

        $this->stripeService->handlePaymentFailed($paymentIntent);
    }

    /**
     * Handle payment_intent.canceled event
     */
    protected function handlePaymentIntentCanceled($paymentIntent): void
    {
        Log::info('Stripe Webhook: Payment Intent Canceled', [
            'payment_intent_id' => $paymentIntent->id,
        ]);

        $payment = \App\Models\Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();
        
        if ($payment && $payment->status !== 'canceled') {
            $payment->markAsCanceled();
            
            // Update negotiation
            $negotiation = $payment->negotiation;
            if ($negotiation) {
                $negotiation->payment_status = 'unpaid';
                $negotiation->save();
            }
        }
    }

    /**
     * Handle payment_intent.requires_action event
     */
    protected function handlePaymentIntentRequiresAction($paymentIntent): void
    {
        Log::info('Stripe Webhook: Payment Intent Requires Action', [
            'payment_intent_id' => $paymentIntent->id,
            'next_action' => $paymentIntent->next_action->type ?? null,
        ]);

        $payment = \App\Models\Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();
        
        if ($payment && $payment->status !== 'requires_action') {
            $payment->status = 'requires_action';
            $payment->save();
        }
    }

    /**
     * Handle payment_intent.processing event
     */
    protected function handlePaymentIntentProcessing($paymentIntent): void
    {
        Log::info('Stripe Webhook: Payment Intent Processing', [
            'payment_intent_id' => $paymentIntent->id,
        ]);

        $payment = \App\Models\Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();
        
        if ($payment && $payment->status !== 'processing') {
            $payment->status = 'processing';
            $payment->save();
        }
    }

    /**
     * Handle charge.refunded event
     */
    protected function handleChargeRefunded($charge): void
    {
        Log::info('Stripe Webhook: Charge Refunded', [
            'charge_id' => $charge->id,
            'payment_intent_id' => $charge->payment_intent,
            'amount_refunded' => $charge->amount_refunded / 100,
        ]);

        $payment = \App\Models\Payment::where('stripe_payment_intent_id', $charge->payment_intent)->first();
        
        if ($payment && !$payment->isRefunded()) {
            $payment->status = 'refunded';
            $payment->refunded_at = now();
            $payment->save();

            // Reverse wallet balances
            $payment->reverseWalletBalances();

            // Update negotiation
            $negotiation = $payment->negotiation;
            if ($negotiation) {
                $negotiation->payment_status = 'refunded';
                $negotiation->save();
            }
        }
    }
}
