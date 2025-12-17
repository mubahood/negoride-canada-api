<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayoutAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payout_accounts';

    protected $fillable = [
        'user_id',
        'account_type',
        'status',
        'stripe_account_id',
        'stripe_person_id',
        'onboarding_completed',
        'charges_enabled',
        'payouts_enabled',
        'details_submitted',
        'bank_account_last4',
        'bank_account_type',
        'bank_account_country',
        'bank_name',
        'card_last4',
        'card_brand',
        'card_country',
        'verification_status',
        'verification_fields_needed',
        'requirements_currently_due',
        'requirements_eventually_due',
        'requirements_past_due',
        'requirements_due_by',
        'default_payout_method',
        'default_currency',
        'minimum_payout_amount',
        'business_name',
        'business_type',
        'business_profile',
        'email',
        'phone',
        'country',
        'stripe_dashboard_url',
        'last_stripe_sync',
        'metadata',
        'admin_notes',
        'activated_at',
        'disabled_at',
        'disabled_reason',
    ];

    protected $casts = [
        'onboarding_completed' => 'boolean',
        'charges_enabled' => 'boolean',
        'payouts_enabled' => 'boolean',
        'details_submitted' => 'boolean',
        'minimum_payout_amount' => 'decimal:2',
        'verification_fields_needed' => 'array',
        'requirements_currently_due' => 'array',
        'requirements_eventually_due' => 'array',
        'requirements_past_due' => 'array',
        'business_profile' => 'array',
        'metadata' => 'array',
        'requirements_due_by' => 'datetime',
        'last_stripe_sync' => 'datetime',
        'activated_at' => 'datetime',
        'disabled_at' => 'datetime',
    ];

    protected $dates = [
        'requirements_due_by',
        'last_stripe_sync',
        'activated_at',
        'disabled_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Relationship with User (Driver)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship with Driver (admin_users table)
     */
    public function driver()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Check if account is fully active and ready for payouts
     */
    public function isActive()
    {
        return $this->status === 'active' 
            && $this->payouts_enabled 
            && $this->onboarding_completed;
    }

    /**
     * Check if account can receive instant payouts
     */
    public function canReceiveInstantPayouts()
    {
        return $this->isActive() 
            && !empty($this->card_last4) 
            && $this->default_payout_method === 'instant';
    }

    /**
     * Check if account has pending requirements
     */
    public function hasPendingRequirements()
    {
        return !empty($this->requirements_currently_due) 
            || !empty($this->requirements_past_due);
    }

    /**
     * Check if account is restricted
     */
    public function isRestricted()
    {
        return $this->status === 'restricted' || !$this->charges_enabled;
    }

    /**
     * Get or create payout account for a driver
     */
    public static function getOrCreateForDriver($userId)
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'status' => 'pending',
                'account_type' => 'express',
                'verification_status' => 'unverified',
                'default_payout_method' => 'standard',
                'default_currency' => 'CAD',
                'minimum_payout_amount' => 10.00,
                'country' => 'CA',
            ]
        );
    }

    /**
     * Mark account as activated
     */
    public function activate()
    {
        $this->update([
            'status' => 'active',
            'activated_at' => now(),
        ]);
    }

    /**
     * Disable account with reason
     */
    public function disable($reason = null)
    {
        $this->update([
            'status' => 'disabled',
            'disabled_at' => now(),
            'disabled_reason' => $reason,
        ]);
    }

    /**
     * Update from Stripe account object
     */
    public function syncFromStripe($stripeAccount)
    {
        $this->update([
            'stripe_account_id' => $stripeAccount->id,
            'charges_enabled' => $stripeAccount->charges_enabled ?? false,
            'payouts_enabled' => $stripeAccount->payouts_enabled ?? false,
            'details_submitted' => $stripeAccount->details_submitted ?? false,
            'requirements_currently_due' => $stripeAccount->requirements->currently_due ?? [],
            'requirements_eventually_due' => $stripeAccount->requirements->eventually_due ?? [],
            'requirements_past_due' => $stripeAccount->requirements->past_due ?? [],
            'requirements_due_by' => isset($stripeAccount->requirements->current_deadline) 
                ? date('Y-m-d H:i:s', $stripeAccount->requirements->current_deadline) 
                : null,
            'last_stripe_sync' => now(),
        ]);

        // Update status based on Stripe account status
        if ($this->payouts_enabled && $this->charges_enabled && $this->details_submitted) {
            if ($this->status !== 'active') {
                $this->activate();
            }
        } elseif (!empty($stripeAccount->requirements->currently_due) || !empty($stripeAccount->requirements->past_due)) {
            $this->update(['status' => 'restricted']);
        }

        return $this;
    }

    /**
     * Update banking information
     */
    public function updateBankingInfo($bankData)
    {
        $this->update([
            'bank_account_last4' => $bankData['last4'] ?? null,
            'bank_account_type' => $bankData['type'] ?? null,
            'bank_account_country' => $bankData['country'] ?? 'CA',
            'bank_name' => $bankData['bank_name'] ?? null,
        ]);
    }

    /**
     * Update card information (for instant payouts)
     */
    public function updateCardInfo($cardData)
    {
        $this->update([
            'card_last4' => $cardData['last4'] ?? null,
            'card_brand' => $cardData['brand'] ?? null,
            'card_country' => $cardData['country'] ?? null,
        ]);
    }

    /**
     * Scope: Active accounts only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where('payouts_enabled', true);
    }

    /**
     * Scope: Pending verification
     */
    public function scopePendingVerification($query)
    {
        return $query->where('verification_status', 'pending')
                     ->orWhere('status', 'pending');
    }

    /**
     * Scope: Has pending requirements
     */
    public function scopeHasRequirements($query)
    {
        return $query->whereNotNull('requirements_currently_due')
                     ->orWhereNotNull('requirements_past_due');
    }

    /**
     * Accessor: Get full status description
     */
    public function getStatusDescriptionAttribute()
    {
        $statuses = [
            'pending' => 'Pending Setup',
            'active' => 'Active & Ready',
            'restricted' => 'Restricted - Action Required',
            'disabled' => 'Disabled',
            'rejected' => 'Rejected',
        ];

        return $statuses[$this->status] ?? 'Unknown';
    }

    /**
     * Accessor: Get verification status description
     */
    public function getVerificationStatusDescriptionAttribute()
    {
        $statuses = [
            'unverified' => 'Not Verified',
            'pending' => 'Verification Pending',
            'verified' => 'Verified',
            'failed' => 'Verification Failed',
        ];

        return $statuses[$this->verification_status] ?? 'Unknown';
    }

    /**
     * Accessor: Check if onboarding is complete
     */
    public function getIsOnboardingCompleteAttribute()
    {
        return $this->onboarding_completed && !$this->hasPendingRequirements();
    }

    /**
     * Accessor: Get payout method description
     */
    public function getPayoutMethodDescriptionAttribute()
    {
        return $this->default_payout_method === 'instant' 
            ? 'Instant Payout (1% fee)' 
            : 'Standard Payout (2-3 days)';
    }
}
