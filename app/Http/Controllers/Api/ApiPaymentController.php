<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StripeService;
use App\Models\Payment;
use App\Models\AdminUser;
use App\Models\Negotiation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ApiPaymentController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Initialize payment for a negotiation
     * POST /api/payments/initiate
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'negotiation_id' => 'required|exists:negotiations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $negotiationId = $request->negotiation_id;

            // Get negotiation with related users
            $negotiation = Negotiation::with(['customer', 'driver'])->find($negotiationId);

            if (!$negotiation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Negotiation not found'
                ], 404);
            }

            // Verify negotiation is accepted
            if ($negotiation->status !== 'Accepted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment can only be initiated for accepted negotiations'
                ], 400);
            }

            // Check if payment already exists
            if ($negotiation->payment_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment already initiated for this negotiation'
                ], 400);
            }

            // Validate agreed_price exists
            if (!$negotiation->agreed_price || $negotiation->agreed_price <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No agreed price found for this negotiation. Please agree on a price first.'
                ], 400);
            }

            // Use agreed_price from negotiation
            $amount = $negotiation->agreed_price;

            // Get customer and driver
            $customer = $negotiation->customer;
            $driver = $negotiation->driver;

            if (!$customer || !$driver) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer or driver not found'
                ], 404);
            }

            // Create payment intent
            $paymentIntent = $this->stripeService->createPaymentIntent(
                $amount,
                $customer,
                $driver,
                $negotiationId,
                [
                    'pickup_location' => $negotiation->pickup_location ?? '',
                    'dropoff_location' => $negotiation->dropoff_location ?? '',
                ]
            );

            if (!$paymentIntent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create payment intent'
                ], 500);
            }

            // Get the created payment record
            $payment = Payment::where('stripe_payment_intent_id', $paymentIntent->id)->first();

            // Update negotiation
            $negotiation->agreed_price = $amount;
            $negotiation->payment_status = 'pending';
            $negotiation->payment_id = $payment->id;
            $negotiation->save();

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'amount' => $payment->amount,
                    'service_fee' => $payment->service_fee,
                    'driver_amount' => $payment->driver_amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while initiating payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify payment status
     * GET /api/payments/{paymentId}/verify
     */
    public function verifyPayment(Request $request, $paymentId): JsonResponse
    {
        try {
            $payment = Payment::with(['negotiation', 'customer', 'driver'])->find($paymentId);

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            // Retrieve latest status from Stripe
            $paymentIntent = $this->stripeService->retrievePaymentIntent($payment->stripe_payment_intent_id);

            if ($paymentIntent) {
                // Sync status with database
                $statusMap = [
                    'succeeded' => 'succeeded',
                    'processing' => 'processing',
                    'requires_payment_method' => 'pending',
                    'requires_confirmation' => 'pending',
                    'requires_action' => 'requires_action',
                    'canceled' => 'canceled',
                ];

                $newStatus = $statusMap[$paymentIntent->status] ?? $payment->status;

                if ($newStatus !== $payment->status) {
                    $payment->status = $newStatus;
                    $payment->save();
                }

                // If payment succeeded, process it
                if ($paymentIntent->status === 'succeeded' && $payment->status !== 'succeeded') {
                    $this->stripeService->handlePaymentSuccess($paymentIntent);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'paid_at' => $payment->paid_at,
                    'negotiation_id' => $payment->negotiation_id,
                    'stripe_status' => $paymentIntent->status ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while verifying payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment history for a user
     * GET /api/payments/history
     */
    public function paymentHistory(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $userType = $request->input('user_type', 'customer'); // 'customer' or 'driver'
            $perPage = $request->input('per_page', 15);

            $query = Payment::with(['negotiation', 'customer', 'driver']);

            if ($userType === 'customer') {
                $query->where('customer_id', $userId);
            } else {
                $query->where('driver_id', $userId);
            }

            $payments = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $payments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'service_fee' => $payment->service_fee,
                        'driver_amount' => $payment->driver_amount,
                        'currency' => $payment->currency,
                        'status' => $payment->status,
                        'payment_type' => $payment->payment_type,
                        'description' => $payment->description,
                        'paid_at' => $payment->paid_at,
                        'created_at' => $payment->created_at,
                        'negotiation' => [
                            'id' => $payment->negotiation->id ?? null,
                            'pickup_location' => $payment->negotiation->pickup_location ?? null,
                            'dropoff_location' => $payment->negotiation->dropoff_location ?? null,
                        ],
                    ];
                }),
                'pagination' => [
                    'current_page' => $payments->currentPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                    'last_page' => $payments->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching payment history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment details
     * GET /api/payments/{paymentId}
     */
    public function getPaymentDetails($paymentId): JsonResponse
    {
        try {
            $payment = Payment::with(['negotiation', 'customer', 'driver', 'transactions'])->find($paymentId);

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $payment->id,
                    'negotiation_id' => $payment->negotiation_id,
                    'customer' => [
                        'id' => $payment->customer->id ?? null,
                        'name' => $payment->customer->name ?? null,
                    ],
                    'driver' => [
                        'id' => $payment->driver->id ?? null,
                        'name' => $payment->driver->name ?? null,
                    ],
                    'amount' => $payment->amount,
                    'service_fee' => $payment->service_fee,
                    'driver_amount' => $payment->driver_amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'payment_type' => $payment->payment_type,
                    'description' => $payment->description,
                    'stripe_payment_intent_id' => $payment->stripe_payment_intent_id,
                    'paid_at' => $payment->paid_at,
                    'failed_at' => $payment->failed_at,
                    'refunded_at' => $payment->refunded_at,
                    'created_at' => $payment->created_at,
                    'transactions' => $payment->transactions->map(function ($transaction) {
                        return [
                            'id' => $transaction->id,
                            'type' => $transaction->type,
                            'category' => $transaction->category,
                            'amount' => $transaction->amount,
                            'description' => $transaction->description,
                            'created_at' => $transaction->created_at,
                        ];
                    }),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching payment details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request refund for a payment
     * POST /api/payments/{paymentId}/refund
     */
    public function refundPayment(Request $request, $paymentId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $payment = Payment::find($paymentId);

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            // Check if payment is refundable
            if ($payment->status !== 'succeeded') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only successful payments can be refunded'
                ], 400);
            }

            if ($payment->isRefunded()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment has already been refunded'
                ], 400);
            }

            $refundAmount = $request->input('amount');
            $reason = $request->input('reason', 'requested_by_customer');

            // Create refund
            $refund = $this->stripeService->createRefund(
                $payment->stripe_payment_intent_id,
                $refundAmount,
                $reason
            );

            if (!$refund) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process refund'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'data' => [
                    'refund_id' => $refund->id,
                    'amount' => $refund->amount / 100, // Convert from cents
                    'currency' => $refund->currency,
                    'status' => $refund->status,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing refund',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a pending payment
     * POST /api/payments/{paymentId}/cancel
     */
    public function cancelPayment($paymentId): JsonResponse
    {
        try {
            $payment = Payment::find($paymentId);

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            // Check if payment is cancelable
            if (!in_array($payment->status, ['pending', 'requires_action'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending payments can be canceled'
                ], 400);
            }

            // Cancel payment intent on Stripe
            $paymentIntent = $this->stripeService->cancelPaymentIntent($payment->stripe_payment_intent_id);

            if (!$paymentIntent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel payment'
                ], 500);
            }

            // Update payment status
            $payment->markAsCanceled();

            // Update negotiation
            $negotiation = $payment->negotiation;
            if ($negotiation) {
                $negotiation->payment_status = 'unpaid';
                $negotiation->payment_id = null;
                $negotiation->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment canceled successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while canceling payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get payment by negotiation ID
     * GET /api/payments/by-negotiation/{negotiationId}
     */
    public function getPaymentByNegotiation($negotiationId): JsonResponse
    {
        try {
            $payment = Payment::where('negotiation_id', $negotiationId)
                ->with(['customer', 'driver', 'transactions'])
                ->first();
            
            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'No payment found for this negotiation'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $payment
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
