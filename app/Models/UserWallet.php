<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wallet_balance',
        'total_earnings',
        'stripe_customer_id',
        'stripe_account_id',
    ];

    protected $casts = [
        'wallet_balance' => 'decimal:2',
        'total_earnings' => 'decimal:2',
    ];

    /**
     * User relationship
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Increment wallet balance
     */
    public function incrementBalance(float $amount): void
    {
        $this->wallet_balance += $amount;
        $this->save();
    }

    /**
     * Decrement wallet balance
     */
    public function decrementBalance(float $amount): void
    {
        $this->wallet_balance -= $amount;
        $this->save();
    }

    /**
     * Add to total earnings
     */
    public function addEarnings(float $amount): void
    {
        $this->total_earnings += $amount;
        $this->save();
    }

    /**
     * Get formatted balance
     */
    public function getFormattedBalanceAttribute(): string
    {
        return '$' . number_format($this->wallet_balance, 2);
    }

    /**
     * Get formatted earnings
     */
    public function getFormattedEarningsAttribute(): string
    {
        return '$' . number_format($this->total_earnings, 2);
    }
}
