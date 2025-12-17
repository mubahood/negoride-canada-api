<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayoutAccount;
use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Stripe\Stripe;
use Stripe\Account;
use Stripe\AccountLink;

class PayoutAccountController extends Controller
{
    use ApiResponser;

    public function __construct()
    {
        // Initialize Stripe with secret key from .env
        Stripe::setApiKey(env('STRIPE_SECRET_KEY'));
    }

    /**
     * Get authenticated user's payout account
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAccount()
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return $this->error('Unauthorized');
            }

            // Get or create payout account
            $account = PayoutAccount::getOrCreateForDriver($user->id);

            $data = [
                'id' => $account->id,
                'user_id' => $account->user_id,
                'account_type' => $account->account_type,
                'status' => $account->status,
                'status_description' => $account->status_description,
                'stripe_account_id' => $account->stripe_account_id,
                'onboarding_completed' => $account->onboarding_completed,
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
                'details_submitted' => $account->details_submitted,
                'bank_account_last4' => $account->bank_account_last4,
                'bank_account_type' => $account->bank_account_type,
                'bank_name' => $account->bank_name,
                'card_last4' => $account->card_last4,
                'card_brand' => $account->card_brand,
                'verification_status' => $account->verification_status,
                'verification_status_description' => $account->verification_status_description,
                'requirements_currently_due' => $account->requirements_currently_due,
                'requirements_eventually_due' => $account->requirements_eventually_due,
                'requirements_past_due' => $account->requirements_past_due,
                'requirements_due_by' => $account->requirements_due_by ? $account->requirements_due_by->toDateTimeString() : null,
                'default_payout_method' => $account->default_payout_method,
                'payout_method_description' => $account->payout_method_description,
                'minimum_payout_amount' => (float) $account->minimum_payout_amount,
                'is_active' => $account->isActive(),
                'can_receive_instant_payouts' => $account->canReceiveInstantPayouts(),
                'has_pending_requirements' => $account->hasPendingRequirements(),
                'is_onboarding_complete' => $account->is_onboarding_complete,
                'stripe_dashboard_url' => $account->stripe_dashboard_url,
                'last_stripe_sync' => $account->last_stripe_sync ? $account->last_stripe_sync->toDateTimeString() : null,
                'created_at' => $account->created_at->toDateTimeString(),
                'updated_at' => $account->updated_at->toDateTimeString(),
            ];

            return $this->success($data, 'Payout account retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve payout account: ' . $e->getMessage());
        }
    }

    /**
     * Create Stripe Connect Express account and get onboarding link
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createStripeAccount(Request $request)
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return $this->error('Unauthorized');
            }

            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'phone' => 'nullable|string',
                'business_type' => 'nullable|in:individual,company',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first());
            }

            // Get or create payout account
            $account = PayoutAccount::getOrCreateForDriver($user->id);

            // Check if Stripe account already exists
            if ($account->stripe_account_id) {
                // Sync existing account
                $this->syncStripeAccount($account);
                
                return $this->success([
                    'account' => $account,
                    'message' => 'Stripe account already exists',
                ], 'Account already exists');
            }

            // Create Stripe Connect Express account
            $stripeAccount = Account::create([
                'type' => 'express',
                'country' => 'CA',
                'email' => $request->input('email'),
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
                'business_type' => $request->input('business_type', 'individual'),
                'metadata' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name ?? 'N/A',
                ],
            ]);

            // Update local account with Stripe ID
            $account->update([
                'stripe_account_id' => $stripeAccount->id,
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'business_type' => $request->input('business_type', 'individual'),
            ]);

            // Sync account details
            $account->syncFromStripe($stripeAccount);

            return $this->success([
                'account' => $account,
                'stripe_account_id' => $stripeAccount->id,
            ], 'Stripe account created successfully');

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return $this->error('Stripe error: ' . $e->getMessage());
        } catch (\Exception $e) {
            return $this->error('Failed to create Stripe account: ' . $e->getMessage());
        }
    }

    /**
     * Get Stripe onboarding link
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOnboardingLink(Request $request)
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return $this->error('Unauthorized');
            }

            $account = PayoutAccount::where('user_id', $user->id)->first();

            if (!$account || !$account->stripe_account_id) {
                return $this->error('Stripe account not found. Please create one first.');
            }

            $returnUrl = $request->input('return_url', env('APP_URL') . '/payout-account/complete');
            $refreshUrl = $request->input('refresh_url', env('APP_URL') . '/payout-account/refresh');

            // Create account link for onboarding
            $accountLink = AccountLink::create([
                'account' => $account->stripe_account_id,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding',
            ]);

            // Update dashboard URL
            $account->update([
                'stripe_dashboard_url' => $accountLink->url,
            ]);

            return $this->success([
                'onboarding_url' => $accountLink->url,
                'expires_at' => $accountLink->expires_at,
            ], 'Onboarding link generated successfully');

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return $this->error('Stripe error: ' . $e->getMessage());
        } catch (\Exception $e) {
            return $this->error('Failed to generate onboarding link: ' . $e->getMessage());
        }
    }

    /**
     * Get Stripe Express Dashboard login link
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardLink()
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return $this->error('Unauthorized');
            }

            $account = PayoutAccount::where('user_id', $user->id)->first();

            if (!$account || !$account->stripe_account_id) {
                return $this->error('Stripe account not found');
            }

            // Create login link for Express Dashboard
            $loginLink = \Stripe\Account::createLoginLink($account->stripe_account_id);

            return $this->success([
                'dashboard_url' => $loginLink->url,
            ], 'Dashboard link generated successfully');

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return $this->error('Stripe error: ' . $e->getMessage());
        } catch (\Exception $e) {
            return $this->error('Failed to generate dashboard link: ' . $e->getMessage());
        }
    }

    /**
     * Sync payout account with Stripe
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncAccount()
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return $this->error('Unauthorized');
            }

            $account = PayoutAccount::where('user_id', $user->id)->first();

            if (!$account || !$account->stripe_account_id) {
                return $this->error('Stripe account not found');
            }

            // Fetch latest data from Stripe
            $stripeAccount = Account::retrieve($account->stripe_account_id);

            // Sync local account
            $account->syncFromStripe($stripeAccount);

            // Update banking/card info if available
            if (!empty($stripeAccount->external_accounts->data)) {
                foreach ($stripeAccount->external_accounts->data as $externalAccount) {
                    if ($externalAccount->object === 'bank_account') {
                        $account->updateBankingInfo([
                            'last4' => $externalAccount->last4,
                            'type' => $externalAccount->account_type ?? null,
                            'country' => $externalAccount->country,
                            'bank_name' => $externalAccount->bank_name ?? null,
                        ]);
                    } elseif ($externalAccount->object === 'card') {
                        $account->updateCardInfo([
                            'last4' => $externalAccount->last4,
                            'brand' => $externalAccount->brand,
                            'country' => $externalAccount->country,
                        ]);
                    }
                }
            }

            return $this->success([
                'account' => $account->fresh(),
            ], 'Account synced successfully');

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return $this->error('Stripe error: ' . $e->getMessage());
        } catch (\Exception $e) {
            return $this->error('Failed to sync account: ' . $e->getMessage());
        }
    }

    /**
     * Update payout preferences
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePreferences(Request $request)
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return $this->error('Unauthorized');
            }

            $validator = Validator::make($request->all(), [
                'default_payout_method' => 'nullable|in:standard,instant',
                'minimum_payout_amount' => 'nullable|numeric|min:10',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first());
            }

            $account = PayoutAccount::where('user_id', $user->id)->first();

            if (!$account) {
                return $this->error('Payout account not found');
            }

            $updateData = [];

            if ($request->has('default_payout_method')) {
                $updateData['default_payout_method'] = $request->input('default_payout_method');
            }

            if ($request->has('minimum_payout_amount')) {
                $updateData['minimum_payout_amount'] = $request->input('minimum_payout_amount');
            }

            if (!empty($updateData)) {
                $account->update($updateData);
            }

            return $this->success([
                'account' => $account->fresh(),
            ], 'Preferences updated successfully');

        } catch (\Exception $e) {
            return $this->error('Failed to update preferences: ' . $e->getMessage());
        }
    }

    /**
     * Deactivate payout account
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deactivate(Request $request)
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return $this->error('Unauthorized');
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first());
            }

            $account = PayoutAccount::where('user_id', $user->id)->first();

            if (!$account) {
                return $this->error('Payout account not found');
            }

            $reason = $request->input('reason', 'User requested deactivation');
            $account->disable($reason);

            return $this->success([
                'account' => $account->fresh(),
            ], 'Account deactivated successfully');

        } catch (\Exception $e) {
            return $this->error('Failed to deactivate account: ' . $e->getMessage());
        }
    }

    /**
     * Reactivate payout account
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function reactivate()
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return $this->error('Unauthorized');
            }

            $account = PayoutAccount::where('user_id', $user->id)->first();

            if (!$account) {
                return $this->error('Payout account not found');
            }

            if ($account->status === 'active') {
                return $this->error('Account is already active');
            }

            // Check if Stripe account is in good standing
            if ($account->stripe_account_id) {
                $stripeAccount = Account::retrieve($account->stripe_account_id);
                
                if (!$stripeAccount->payouts_enabled) {
                    return $this->error('Stripe account does not have payouts enabled');
                }
            }

            $account->activate();

            return $this->success([
                'account' => $account->fresh(),
            ], 'Account reactivated successfully');

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return $this->error('Stripe error: ' . $e->getMessage());
        } catch (\Exception $e) {
            return $this->error('Failed to reactivate account: ' . $e->getMessage());
        }
    }

    /**
     * Helper: Sync Stripe account details
     */
    private function syncStripeAccount($account)
    {
        try {
            if ($account->stripe_account_id) {
                $stripeAccount = Account::retrieve($account->stripe_account_id);
                $account->syncFromStripe($stripeAccount);
            }
        } catch (\Exception $e) {
            // Silent fail - sync is optional
        }
    }
}
