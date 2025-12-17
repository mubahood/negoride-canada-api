<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Models\PayoutAccount;
use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\ApiResponser;

class PayoutRequestController extends Controller
{
    use ApiResponser;

    /**
     * Get all payout requests for authenticated user
     */
    public function index(Request $request)
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $payoutRequests = PayoutRequest::forUser($user->id)
                ->with(['payoutAccount'])
                ->recent()
                ->get();

            return $this->success('Payout requests retrieved successfully', $payoutRequests);
        } catch (\Exception $e) {
            return $this->error('Error retrieving payout requests: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get a single payout request
     */
    public function show($id)
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $payoutRequest = PayoutRequest::forUser($user->id)
                ->with(['payoutAccount'])
                ->find($id);

            if (!$payoutRequest) {
                return $this->error('Payout request not found', 404);
            }

            return $this->success('Payout request retrieved successfully', $payoutRequest);
        } catch (\Exception $e) {
            return $this->error('Error retrieving payout request: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new payout request
     */
    public function store(Request $request)
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            // Validate request
            $validated = $request->validate([
                'amount' => 'required|numeric|min:10',
                'payout_method' => 'nullable|in:standard,instant',
                'description' => 'nullable|string|max:500',
            ]);

            // Get user's payout account
            $payoutAccount = PayoutAccount::where('user_id', $user->id)->first();

            if (!$payoutAccount) {
                return $this->error('You must setup a payout account first', 400);
            }

            if (!$payoutAccount->isActive()) {
                return $this->error('Your payout account is not active yet. Please complete onboarding first.', 400);
            }

            // Check minimum amount
            $amount = $validated['amount'];
            $minimumAmount = $payoutAccount->minimum_payout_amount ?? 10;

            if ($amount < $minimumAmount) {
                return $this->error("Minimum payout amount is $minimumAmount", 400);
            }

            // TODO: Check user's available balance
            // For now, we'll assume the user has sufficient balance
            // In production, you'd check against actual earnings/balance
            
            $payoutMethod = $validated['payout_method'] ?? $payoutAccount->default_payout_method ?? 'standard';
            
            // Check if instant payouts are available
            if ($payoutMethod === 'instant' && !$payoutAccount->can_receive_instant_payouts) {
                return $this->error('Instant payouts are not available for your account', 400);
            }

            // Create payout request
            $payoutRequest = PayoutRequest::createRequest(
                $user->id,
                $payoutAccount->id,
                $amount,
                $payoutMethod,
                $validated['description'] ?? null
            );

            // Load relationships
            $payoutRequest->load('payoutAccount');

            return $this->success('Payout request created successfully', $payoutRequest, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('Validation error: ' . json_encode($e->errors()), 422);
        } catch (\Exception $e) {
            return $this->error('Error creating payout request: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancel a payout request
     */
    public function cancel($id)
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $payoutRequest = PayoutRequest::forUser($user->id)->find($id);

            if (!$payoutRequest) {
                return $this->error('Payout request not found', 404);
            }

            if (!$payoutRequest->can_cancel) {
                return $this->error('This payout request cannot be cancelled', 400);
            }

            $payoutRequest->cancel();

            return $this->success('Payout request cancelled successfully', $payoutRequest);
        } catch (\Exception $e) {
            return $this->error('Error cancelling payout request: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get payout statistics
     */
    public function statistics()
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return $this->error('Unauthorized', 401);
            }

            $stats = [
                'total_requests' => PayoutRequest::forUser($user->id)->count(),
                'pending_requests' => PayoutRequest::forUser($user->id)->pending()->count(),
                'completed_requests' => PayoutRequest::forUser($user->id)->completed()->count(),
                'failed_requests' => PayoutRequest::forUser($user->id)->failed()->count(),
                'total_paid_out' => PayoutRequest::forUser($user->id)->completed()->sum('net_amount'),
                'total_fees_paid' => PayoutRequest::forUser($user->id)->completed()->sum('fee_amount'),
                
                // TODO: Add actual balance tracking
                'available_balance' => 0, // Placeholder - integrate with your earnings system
                'pending_balance' => 0,   // Placeholder - integrate with your earnings system
            ];

            return $this->success('Statistics retrieved successfully', $stats);
        } catch (\Exception $e) {
            return $this->error('Error retrieving statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * ADMIN ONLY: Process a payout request
     * This would be called by admin/automated system to actually process the payout
     */
    public function process($id, Request $request)
    {
        try {
            // TODO: Add admin authentication check
            
            $payoutRequest = PayoutRequest::find($id);

            if (!$payoutRequest) {
                return $this->error('Payout request not found', 404);
            }

            if (!$payoutRequest->isProcessable()) {
                return $this->error('This payout request cannot be processed', 400);
            }

            // Mark as processing
            $payoutRequest->markAsProcessing();

            // TODO: Integrate with Stripe to create actual transfer
            // For now, we'll just mark as completed with dummy data
            
            // Simulate Stripe transfer
            $stripeTransferId = 'tr_dummy_' . time();
            $stripePayoutId = 'po_dummy_' . time();

            $payoutRequest->markAsCompleted($stripeTransferId, $stripePayoutId);

            return $this->success('Payout request processed successfully', $payoutRequest);
        } catch (\Exception $e) {
            // Mark as failed if error occurs
            if (isset($payoutRequest)) {
                $payoutRequest->markAsFailed($e->getMessage());
            }
            
            return $this->error('Error processing payout request: ' . $e->getMessage(), 500);
        }
    }

    /**
     * ADMIN ONLY: Get all payout requests (across all users)
     */
    public function adminIndex(Request $request)
    {
        try {
            // TODO: Add admin authentication check
            
            $status = $request->get('status');
            $userId = $request->get('user_id');

            $query = PayoutRequest::with(['user', 'payoutAccount']);

            if ($status) {
                $query->where('status', $status);
            }

            if ($userId) {
                $query->where('user_id', $userId);
            }

            $payoutRequests = $query->recent()->paginate(50);

            return $this->success('Payout requests retrieved successfully', $payoutRequests);
        } catch (\Exception $e) {
            return $this->error('Error retrieving payout requests: ' . $e->getMessage(), 500);
        }
    }
}
