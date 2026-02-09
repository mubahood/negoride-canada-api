<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserWallet;
use App\Models\Transaction;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class WalletController extends Controller
{
    use ApiResponser;

    protected function getAuthUser()
    {
        $user = request()->user();
        if ($user) return $user;

        $user = request()->get('auth_user');
        if ($user) return $user;

        $user = auth('api')->user();
        if ($user) return $user;

        try {
            $user = JWTAuth::parseToken()->authenticate();
            if ($user) return $user;
        } catch (\Exception $e) {}

        $userId = request()->input('user_id') ?? request()->get('user_id');
        if ($userId) {
            $user = \Encore\Admin\Auth\Database\Administrator::find($userId);
            if ($user) return $user;
        }

        return null;
    }
    /**
     * Get authenticated user's wallet
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWallet()
    {
        try {
            $user = $this->getAuthUser();
            
            if (!$user) {
                return $this->error('Unauthorized');
            }

            // Get or create wallet
            $wallet = $user->getOrCreateWallet();

            $data = [
                'id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'wallet_balance' => (float) $wallet->wallet_balance,
                'total_earnings' => (float) $wallet->total_earnings,
                'stripe_customer_id' => $wallet->stripe_customer_id,
                'stripe_account_id' => $wallet->stripe_account_id,
                'created_at' => $wallet->created_at->toDateTimeString(),
                'updated_at' => $wallet->updated_at->toDateTimeString(),
            ];

            return $this->success($data, 'Wallet retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve wallet: ' . $e->getMessage());
        }
    }

    /**
     * Get user's transaction history
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTransactions(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            
            if (!$user) {
                return $this->error('Unauthorized');
            }

            $category = $request->input('category');
            $type = $request->input('type'); // 'credit' or 'debit'
            $negotiationId = $request->input('negotiation_id');

            // Build query
            $query = Transaction::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($category) {
                $query->where('category', $category);
            }

            if ($type === 'credit') {
                $query->credits();
            } elseif ($type === 'debit') {
                $query->debits();
            }

            if ($negotiationId) {
                $query->where('negotiation_id', $negotiationId);
            }

            // Get all transactions
            $transactions = $query->get();

            // Format response
            $data = $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'user_id' => $transaction->user_id,
                    'type' => $transaction->type,
                    'category' => $transaction->category,
                    'amount' => (float) $transaction->amount,
                    'balance_before' => (float) $transaction->balance_before,
                    'balance_after' => (float) $transaction->balance_after,
                    'reference' => $transaction->reference ?? '',
                    'description' => $transaction->description ?? '',
                    'status' => $transaction->status,
                    'negotiation_id' => $transaction->negotiation_id ?? 0,
                    'payment_id' => $transaction->payment_id ?? 0,
                    'metadata' => $transaction->metadata ? json_encode($transaction->metadata) : '',
                    'created_at' => $transaction->created_at->toDateTimeString(),
                    'updated_at' => $transaction->updated_at->toDateTimeString(),
                ];
            });

            return $this->success($data, 'Transactions retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve transactions: ' . $e->getMessage());
        }
    }

    /**
     * Get wallet summary with statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWalletSummary()
    {
        try {
            $user = $this->getAuthUser();
            
            if (!$user) {
                return $this->error('Unauthorized');
            }

            $wallet = $user->getOrCreateWallet();
            
            // Get transaction statistics
            $totalCredits = Transaction::where('user_id', $user->id)
                ->where('type', 'credit')
                ->sum('amount');
            
            $totalDebits = Transaction::where('user_id', $user->id)
                ->where('type', 'debit')
                ->sum('amount');
            
            $totalTransactions = Transaction::where('user_id', $user->id)->count();
            
            $rideEarnings = Transaction::where('user_id', $user->id)
                ->where('category', 'ride_earning')
                ->sum('amount');
            
            // Get recent transactions
            $recentTransactions = Transaction::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'type' => $transaction->type,
                        'category' => $transaction->category,
                        'amount' => (float) $transaction->amount,
                        'description' => $transaction->description,
                        'created_at' => $transaction->created_at->toDateTimeString(),
                    ];
                });

            $data = [
                'wallet' => [
                    'balance' => (float) $wallet->wallet_balance,
                    'total_earnings' => (float) $wallet->total_earnings,
                ],
                'statistics' => [
                    'total_credits' => (float) $totalCredits,
                    'total_debits' => (float) $totalDebits,
                    'total_transactions' => $totalTransactions,
                    'ride_earnings' => (float) $rideEarnings,
                ],
                'recent_transactions' => $recentTransactions,
            ];

            return $this->success($data, 'Wallet summary retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve wallet summary: ' . $e->getMessage());
        }
    }

    /**
     * Get earnings statistics
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEarningsStats(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            
            if (!$user) {
                return $this->error('Unauthorized');
            }

            $period = $request->input('period', 'all'); // 'today', 'week', 'month', 'year', 'all'

            $query = Transaction::where('user_id', $user->id)
                ->where('category', 'ride_earning');

            // Apply date filter
            switch ($period) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'week':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereMonth('created_at', now()->month)
                          ->whereYear('created_at', now()->year);
                    break;
                case 'year':
                    $query->whereYear('created_at', now()->year);
                    break;
            }

            $earnings = $query->sum('amount');
            $count = $query->count();

            $data = [
                'period' => $period,
                'total_earnings' => (float) $earnings,
                'trip_count' => $count,
                'average_per_trip' => $count > 0 ? (float) ($earnings / $count) : 0,
            ];

            return $this->success($data, 'Earnings statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve earnings stats: ' . $e->getMessage());
        }
    }
}
