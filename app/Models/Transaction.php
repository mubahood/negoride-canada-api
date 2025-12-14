<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'user_type',
        'payment_id',
        'type',
        'category',
        'amount',
        'balance_before',
        'balance_after',
        'reference',
        'description',
        'status',
        'related_user_id',
        'negotiation_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(Administrator::class, 'user_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function relatedUser()
    {
        return $this->belongsTo(Administrator::class, 'related_user_id');
    }

    public function negotiation()
    {
        return $this->belongsTo(Negotiation::class);
    }

    // Scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCredits($query)
    {
        return $query->where('type', 'credit');
    }

    public function scopeDebits($query)
    {
        return $query->where('type', 'debit');
    }

    public function scopeOfCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // Helper methods
    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }

    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }

    public function getFormattedAmountAttribute(): string
    {
        return 'CAD $' . number_format($this->amount, 2);
    }
}

