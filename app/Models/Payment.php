<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'negotiation_id',
        'customer_id',
        'driver_id',
        'stripe_payment_intent_id',
        'stripe_customer_id',
        'stripe_payment_method',
        'amount',
        'service_fee',
        'driver_amount',
        'status',
        'payment_type',
        'currency',
        'description',
        'failure_reason',
        'metadata',
        'paid_at',
        'failed_at',
        'refunded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'driver_amount' => 'decimal:2',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    // Relationships
    public function negotiation()
    {
        return $this->belongsTo(Negotiation::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // Scopes
    public function scopeSucceeded($query)
    {
        return $query->where('status', 'succeeded');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // Status methods
    public function isSuccessful(): bool
    {
        return $this->status === 'succeeded';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    // State transitions
    public function markAsPaid()
    {
        DB::beginTransaction();
        
        try {
            $this->update([
                'status' => 'succeeded',
                'paid_at' => now(),
            ]);

            if ($this->negotiation) {
                $this->negotiation->update([
                    'payment_status' => 'paid',
                    'payment_completed_at' => now(),
                ]);
            }

            $this->createTransactions();
            $this->updateWalletBalances();

            DB::commit();
            return $this;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function markAsFailed(string $reason = null)
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'failed_at' => now(),
        ]);

        if ($this->negotiation) {
            $this->negotiation->update(['payment_status' => 'failed']);
        }

        return $this;
    }

    protected function createTransactions()
    {
        $reference = 'TXN-' . strtoupper(uniqid());

        $customerWallet = $this->customer->wallet;
        $driverWallet = $this->driver->wallet;

        Transaction::create([
            'user_id' => $this->customer_id,
            'user_type' => 'customer',
            'payment_id' => $this->id,
            'type' => 'debit',
            'category' => 'ride_payment',
            'amount' => $this->amount,
            'balance_before' => $customerWallet->wallet_balance ?? 0,
            'balance_after' => $customerWallet->wallet_balance ?? 0,
            'reference' => $reference . '-CUST',
            'description' => 'Payment for ride service',
            'status' => 'completed',
            'related_user_id' => $this->driver_id,
            'negotiation_id' => $this->negotiation_id,
        ]);

        $driverBalanceBefore = $driverWallet->wallet_balance ?? 0;
        $driverBalanceAfter = $driverBalanceBefore + $this->driver_amount;
        
        Transaction::create([
            'user_id' => $this->driver_id,
            'user_type' => 'driver',
            'payment_id' => $this->id,
            'type' => 'credit',
            'category' => 'ride_earning',
            'amount' => $this->driver_amount,
            'balance_before' => $driverBalanceBefore,
            'balance_after' => $driverBalanceAfter,
            'reference' => $reference . '-DRV',
            'description' => 'Earning from ride service',
            'status' => 'completed',
            'related_user_id' => $this->customer_id,
            'negotiation_id' => $this->negotiation_id,
        ]);

        if ($this->service_fee > 0) {
            Transaction::create([
                'user_id' => $this->driver_id,
                'user_type' => 'driver',
                'payment_id' => $this->id,
                'type' => 'debit',
                'category' => 'service_fee',
                'amount' => $this->service_fee,
                'balance_before' => $driverBalanceAfter,
                'balance_after' => $driverBalanceAfter,
                'reference' => $reference . '-FEE',
                'description' => 'Platform service fee',
                'status' => 'completed',
                'negotiation_id' => $this->negotiation_id,
            ]);
        }
    }

    protected function updateWalletBalances()
    {
        $driverWallet = $this->driver->wallet;
        if ($driverWallet) {
            $driverWallet->incrementBalance($this->driver_amount);
            $driverWallet->addEarnings($this->driver_amount);
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (!$payment->currency) {
                $payment->currency = 'cad';
            }
            if (!$payment->description) {
                $payment->description = 'Payment for ride service';
            }
        });
    }
}

