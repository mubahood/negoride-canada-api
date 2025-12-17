<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayoutRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'payout_account_id',
        'amount',
        'currency',
        'fee_amount',
        'net_amount',
        'status',
        'payout_method',
        'stripe_transfer_id',
        'stripe_payout_id',
        'description',
        'admin_notes',
        'failure_reason',
        'requested_at',
        'processing_at',
        'processed_at',
        'failed_at',
        'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'metadata' => 'array',
        'requested_at' => 'datetime',
        'processing_at' => 'datetime',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $appends = [
        'status_color',
        'status_icon',
        'payout_method_description',
        'formatted_amount',
        'can_cancel',
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payoutAccount()
    {
        return $this->belongsTo(PayoutAccount::class);
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('requested_at', 'desc');
    }

    /**
     * Helper Methods
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isProcessing()
    {
        return $this->status === 'processing';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isFailed()
    {
        return $this->status === 'failed';
    }

    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    public function getCanCancelAttribute()
    {
        return in_array($this->status, ['pending', 'failed']);
    }

    public function isProcessable()
    {
        return $this->status === 'pending';
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            'pending' => '#FFA500',    // Orange
            'processing' => '#007AFF', // Blue
            'completed' => '#34C759',  // Green
            'failed' => '#FF3B30',     // Red
            'cancelled' => '#8E8E93',  // Gray
        ];

        return $colors[$this->status] ?? '#8E8E93';
    }

    public function getStatusIconAttribute()
    {
        $icons = [
            'pending' => '⏳',
            'processing' => '🔄',
            'completed' => '✅',
            'failed' => '❌',
            'cancelled' => '🚫',
        ];

        return $icons[$this->status] ?? '⏳';
    }

    public function getPayoutMethodDescriptionAttribute()
    {
        return $this->payout_method === 'instant' 
            ? 'Instant Payout (30 mins)' 
            : 'Standard Payout (2-3 days)';
    }

    public function getFormattedAmountAttribute()
    {
        return $this->currency . ' ' . number_format($this->amount, 2);
    }

    /**
     * Status Transition Methods
     */
    public function markAsProcessing()
    {
        $this->update([
            'status' => 'processing',
            'processing_at' => now(),
        ]);
    }

    public function markAsCompleted($stripeTransferId = null, $stripePayoutId = null)
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
            'stripe_transfer_id' => $stripeTransferId ?? $this->stripe_transfer_id,
            'stripe_payout_id' => $stripePayoutId ?? $this->stripe_payout_id,
        ]);
    }

    public function markAsFailed($reason)
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);
    }

    public function cancel()
    {
        if (!$this->can_cancel) {
            throw new \Exception('This payout request cannot be cancelled');
        }

        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Calculate fee based on payout method
     */
    public static function calculateFee($amount, $payoutMethod = 'standard')
    {
        if ($payoutMethod === 'instant') {
            return round($amount * 0.01, 2); // 1% fee for instant
        }
        
        return 0; // Standard is free
    }

    /**
     * Calculate net amount after fees
     */
    public static function calculateNetAmount($amount, $payoutMethod = 'standard')
    {
        $fee = self::calculateFee($amount, $payoutMethod);
        return round($amount - $fee, 2);
    }

    /**
     * Create a new payout request
     */
    public static function createRequest($userId, $payoutAccountId, $amount, $payoutMethod = 'standard', $description = null)
    {
        $feeAmount = self::calculateFee($amount, $payoutMethod);
        $netAmount = self::calculateNetAmount($amount, $payoutMethod);

        return self::create([
            'user_id' => $userId,
            'payout_account_id' => $payoutAccountId,
            'amount' => $amount,
            'currency' => 'USD',
            'fee_amount' => $feeAmount,
            'net_amount' => $netAmount,
            'status' => 'pending',
            'payout_method' => $payoutMethod,
            'description' => $description,
            'requested_at' => now(),
        ]);
    }

    /**
     * Get status description
     */
    public function getStatusDescription()
    {
        $descriptions = [
            'pending' => 'Pending Review',
            'processing' => 'Processing Transfer',
            'completed' => 'Transfer Completed',
            'failed' => 'Transfer Failed',
            'cancelled' => 'Request Cancelled',
        ];

        return $descriptions[$this->status] ?? 'Unknown Status';
    }
}
