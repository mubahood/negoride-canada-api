<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Negotiation extends Model
{
    use HasFactory;

    // Payment Status Constants
    const PAYMENT_STATUS_PENDING = 'pending';
    const PAYMENT_STATUS_PAID = 'paid';
    const PAYMENT_STATUS_FAILED = 'failed';
    const PAYMENT_STATUS_CANCELLED = 'cancelled';
    const PAYMENT_STATUS_REFUNDED = 'refunded';

    // Negotiation Status Constants
    const STATUS_ACTIVE = 'Active';
    const STATUS_ACCEPTED = 'Accepted';
    const STATUS_DECLINED = 'Declined';
    const STATUS_CANCELLED = 'Cancelled';
    const STATUS_COMPLETED = 'Completed';

    // Stripe Payment Status
    const STRIPE_PAID_YES = 'Yes';
    const STRIPE_PAID_NO = 'No';

    /**
     * IMPORTANT: Price Storage Format
     * 
     * The 'agreed_price' field stores amounts in CENTS (not dollars)
     * Examples:
     *   - $1.00 CAD = 100 cents
     *   - $12.50 CAD = 1250 cents
     *   - $0.50 CAD = 50 cents (minimum allowed by Stripe)
     * 
     * Mobile app sends prices in dollars, backend converts to cents
     * before storage in ApiChatController::negotiations_records_create()
     */

    protected $fillable = [
        'customer_id',
        'customer_name',
        'driver_id',
        'driver_name',
        'status',
        'customer_accepted',
        'customer_driver',
        'pickup_lat',
        'pickup_lng',
        'pickup_address',
        'dropoff_lat',
        'dropoff_lng',
        'dropoff_address',
        'records',
        'details',
        'is_active',
        'agreed_price',
        'payment_status',
        'payment_id',
        'payment_completed_at',
        'stripe_id',
        'stripe_url',
        'stripe_product_id',
        'stripe_price_id',
        'stripe_paid',
    ];

    protected $casts = [
        'agreed_price' => 'decimal:2',
        'payment_completed_at' => 'datetime',
    ];

    //boot
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->status = self::STATUS_ACTIVE;
            $model->is_active = "Yes";
        });

        //updating
        static::updating(function ($model) {
            if (
                $model->status == 'Accepted' ||
                $model->status == 'Accept' ||
                $model->status == 'Pending' ||
                $model->status == 'Started' ||
                $model->status == 'Ongoing' ||
                $model->status == 'Active'
            ) {
                $model->is_active = 'Yes';
            } else if (
                $model->status == 'Completed' ||
                $model->status == 'Cancelled' ||
                $model->status == 'Canceled' ||
                $model->status == 'Declined'
            ) {
                $model->is_active = 'No';
            }
        });

        //created 
        static::created(function ($model) {
            if ($model->is_active == 'Yes') {
                $driver = \Encore\Admin\Auth\Database\Administrator::find($model->driver_id);
                if ($driver != null) {
                    $driver->ready_for_trip = 'No';
                    $driver->save();
                }
            }
        });

        //updated
        static::updated(function ($model) {
            if ($model->is_active == 'Yes') {
                $driver = \Encore\Admin\Auth\Database\Administrator::find($model->driver_id);
                if ($driver != null) {
                    $driver->ready_for_trip = 'No';
                    $driver->save();
                }
            }
        });
    }

    //belongs to driver
    public function driver()
    {
        return $this->belongsTo(\Encore\Admin\Auth\Database\Administrator::class, 'driver_id');
    }

    //appends for customer_phone and driver_phone
    protected $appends = ['customer_phone', 'driver_phone'];

    //belongs to customer
    public function customer()
    {
        return $this->belongsTo(\Encore\Admin\Auth\Database\Administrator::class, 'customer_id');
    }

    //has many negotiation records
    public function negotiationRecords()
    {
        return $this->hasMany(NegotiationRecord::class, 'negotiation_id');
    }

    //has one payment
    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    //has many transactions
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * ========================================
     * PAYMENT STATUS VALIDATION & HELPERS
     * ========================================
     */

    /**
     * Check if payment is required for this negotiation
     */
    public function requiresPayment(): bool
    {
        return $this->status === self::STATUS_ACCEPTED && 
               $this->agreed_price > 0 &&
               !$this->isPaid();
    }

    /**
     * Check if payment is completed (comprehensive check)
     */
    public function isPaymentCompleted(): bool
    {
        return $this->isPaid();
    }

    /**
     * Check if negotiation is fully paid
     */
    public function isPaid(): bool
    {
        return $this->stripe_paid === self::STRIPE_PAID_YES || 
               $this->payment_status === self::PAYMENT_STATUS_PAID;
    }

    /**
     * Check if payment is pending
     */
    public function isPaymentPending(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PENDING &&
               $this->stripe_paid !== self::STRIPE_PAID_YES;
    }

    /**
     * Check if payment has failed
     */
    public function isPaymentFailed(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_FAILED;
    }

    /**
     * Check if payment was cancelled
     */
    public function isPaymentCancelled(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_CANCELLED;
    }

    /**
     * Check if payment can be retried
     */
    public function canRetryPayment(): bool
    {
        return $this->requiresPayment() || 
               $this->isPaymentFailed() || 
               $this->isPaymentCancelled();
    }

    /**
     * Check if payment link exists and is valid
     */
    public function hasValidPaymentLink(): bool
    {
        return !empty($this->stripe_url) && 
               !empty($this->stripe_id) &&
               $this->stripe_paid !== self::STRIPE_PAID_YES;
    }

    /**
     * Mark payment as completed
     */
    public function markAsPaid(string $stripePaymentId = null): bool
    {
        try {
            $this->payment_status = self::PAYMENT_STATUS_PAID;
            $this->stripe_paid = self::STRIPE_PAID_YES;
            
            if (!$this->payment_completed_at) {
                $this->payment_completed_at = now();
            }

            if ($stripePaymentId) {
                $this->stripe_id = $stripePaymentId;
            }

            $saved = $this->save();

            if ($saved) {
                Log::info('💰 Negotiation marked as paid', [
                    'negotiation_id' => $this->id,
                    'customer_id' => $this->customer_id,
                    'driver_id' => $this->driver_id,
                    'amount' => $this->agreed_price,
                    'stripe_id' => $this->stripe_id,
                ]);
            }

            return $saved;
        } catch (\Exception $e) {
            Log::error('❌ Failed to mark payment as paid', [
                'negotiation_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Mark payment as failed
     */
    public function markPaymentFailed(string $reason = null): bool
    {
        try {
            $this->payment_status = self::PAYMENT_STATUS_FAILED;
            $this->stripe_paid = self::STRIPE_PAID_NO;
            
            if ($reason) {
                $this->payment_failure_reason = $reason;
            }

            $saved = $this->save();

            if ($saved) {
                Log::warning('⚠️ Payment marked as failed', [
                    'negotiation_id' => $this->id,
                    'reason' => $reason,
                ]);
            }

            return $saved;
        } catch (\Exception $e) {
            Log::error('❌ Failed to mark payment as failed', [
                'negotiation_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Reset payment link (for retry)
     */
    public function resetPaymentLink(): void
    {
        $this->stripe_id = null;
        $this->stripe_url = null;
        $this->stripe_product_id = null;
        $this->stripe_price_id = null;
        
        Log::info('🔄 Payment link reset for retry', [
            'negotiation_id' => $this->id,
        ]);
    }

    /**
     * Validate payment state transition
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $currentStatus = $this->payment_status;

        // Define allowed transitions
        $allowedTransitions = [
            null => [self::PAYMENT_STATUS_PENDING],
            '' => [self::PAYMENT_STATUS_PENDING],
            self::PAYMENT_STATUS_PENDING => [
                self::PAYMENT_STATUS_PAID, 
                self::PAYMENT_STATUS_FAILED,
                self::PAYMENT_STATUS_CANCELLED
            ],
            self::PAYMENT_STATUS_FAILED => [
                self::PAYMENT_STATUS_PENDING,
                self::PAYMENT_STATUS_CANCELLED
            ],
            self::PAYMENT_STATUS_CANCELLED => [
                self::PAYMENT_STATUS_PENDING
            ],
            self::PAYMENT_STATUS_PAID => [
                self::PAYMENT_STATUS_REFUNDED
            ],
            self::PAYMENT_STATUS_REFUNDED => [], // No transitions from refunded
        ];

        return in_array($newStatus, $allowedTransitions[$currentStatus] ?? []);
    }

    /**
     * ========================================
     * ATTRIBUTE ACCESSORS
     * ========================================
     */

    //get customer phone
    public function getCustomerPhoneAttribute()
    {
        if ($this->customer == null) {
            return null;
        }
        return $this->customer->phone_number;
    }

    //get driver phone
    public function getDriverPhoneAttribute()
    {
        if ($this->driver == null) {
            return null;
        }
        return $this->driver->phone_number;
    }

    /**
     * ========================================
     * STRIPE PAYMENT LINK CREATION
     * ========================================
     */

    /**
     * Create Stripe payment link for this negotiation
     * Enhanced with validation, error handling, and state management
     */
    public function create_payment_link()
    {
        try {
            $stripe_key = env('STRIPE_KEY');
            
            if (empty($stripe_key)) {
                throw new \Exception("Stripe API key not configured");
            }
            
            // Check if already has valid payment link
            if ($this->hasValidPaymentLink()) {
                Log::info('ℹ️ Payment link already exists', [
                    'negotiation_id' => $this->id,
                    'stripe_url' => $this->stripe_url,
                ]);
                return;
            }
            
            // CRITICAL: Check if payment link already exists to prevent duplicates
            if (!empty($this->stripe_url) && !empty($this->stripe_id)) {
                Log::info('⚠️ Payment link already exists, skipping generation', [
                    'negotiation_id' => $this->id,
                    'existing_stripe_url' => $this->stripe_url,
                    'existing_stripe_id' => $this->stripe_id
                ]);
                return; // Already has a payment link, don't create another
            }
            
            // Validate agreed price
            if (!$this->agreed_price || $this->agreed_price <= 0) {
                throw new \Exception("No agreed price set for negotiation #{$this->id}");
            }
            
            // Validate payment can be created
            if (!$this->canRetryPayment() && $this->isPaid()) {
                throw new \Exception("Payment already completed for negotiation #{$this->id}");
            }
            
            // Get customer name
            $customer = $this->customer;
            $customer_name = $customer ? $customer->name : 'Customer #' . $this->customer_id;
            
            // Get driver name
            $driver = $this->driver;
            $driver_name = $driver ? $driver->name : 'Driver #' . $this->driver_id;
        
            Log::info('🔄 Creating Stripe payment link', [
                'negotiation_id' => $this->id,
                'amount' => $this->agreed_price,
                'customer' => $customer_name,
                'driver' => $driver_name,
            ]);

            // Amount in cents (Stripe uses smallest currency unit)
            $amount_cents = intval(floatval($this->agreed_price));
            
            // Validate amount
            if ($amount_cents < 50) { // Minimum $0.50 CAD
                throw new \Exception("Payment amount too small: $amount_cents cents");
            }
            
            // Product name: Ride number + customer name
            $product_name = "Ride #{$this->id} - {$customer_name}";
            
            // Product description
            $description = "Trip from {$this->pickup_address} to {$this->dropoff_address}";
            if (strlen($description) > 500) {
                $description = substr($description, 0, 497) . '...';
            }
            
            $stripe = new \Stripe\StripeClient($stripe_key);
            
            // Create a Stripe product for this ride
            $product = $stripe->products->create([
                'name' => $product_name,
                'description' => $description,
                'metadata' => [
                    'negotiation_id' => $this->id,
                    'customer_id' => $this->customer_id,
                    'driver_id' => $this->driver_id,
                    'negoride_source' => 'ride_payment',
                    'customer_name' => $customer_name,
                    'driver_name' => $driver_name,
                ]
            ]);

            Log::info('✅ Stripe product created', ['product_id' => $product->id]);

            // Create a price for this product
            $price = $stripe->prices->create([
                'unit_amount' => $amount_cents,
                'currency' => 'cad',
                'product' => $product->id,
                'metadata' => [
                    'negotiation_id' => $this->id,
                    'agreed_price' => $this->agreed_price
                ]
            ]);

            Log::info('✅ Stripe price created', [
                'price_id' => $price->id,
                'amount_cents' => $amount_cents
            ]);

            // Create payment link using the dynamic price
            $paymentLink = $stripe->paymentLinks->create([
                'line_items' => [
                    [
                        'price' => $price->id,
                        'quantity' => 1,
                    ]
                ],
                'metadata' => [
                    'negotiation_id' => $this->id,
                    'customer_id' => $this->customer_id,
                    'driver_id' => $this->driver_id,
                    'agreed_price' => $this->agreed_price,
                    'customer_name' => $customer_name,
                    'driver_name' => $driver_name,
                ],
                'after_completion' => [
                    'type' => 'redirect',
                    'redirect' => [
                        'url' => env('APP_URL', 'http://localhost:8888/negoride-canada-api') . '/payment-success?negotiation_id=' . $this->id
                    ]
                ]
            ]);
            
            // Update negotiation with payment link details
            $this->stripe_id = $paymentLink->id;
            $this->stripe_url = $paymentLink->url;
            $this->stripe_product_id = $product->id;
            $this->stripe_price_id = $price->id;
            $this->stripe_paid = self::STRIPE_PAID_NO;
            
            // Set payment status to pending if transitioning is allowed
            if ($this->canTransitionTo(self::PAYMENT_STATUS_PENDING)) {
                $this->payment_status = self::PAYMENT_STATUS_PENDING;
            }
            
            $this->save();
            
            Log::info('✅ Payment link created successfully', [
                'negotiation_id' => $this->id,
                'stripe_url' => $this->stripe_url,
                'stripe_id' => $paymentLink->id,
                'amount' => $this->agreed_price,
            ]);
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('❌ Stripe API error', [
                'negotiation_id' => $this->id,
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]);
            
            // Mark payment as failed
            $this->markPaymentFailed('Stripe API error: ' . $e->getMessage());
            
            throw new \Exception("Stripe error: " . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('❌ Payment link creation failed', [
                'negotiation_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            
            $this->markPaymentFailed('System error: ' . $e->getMessage());
            
            throw $e;
        }
    }

    /**
     * CENTRALIZED PAYMENT LINK MANAGEMENT
     * Get existing payment link or create new one if needed
     * This prevents duplicate payment link generation
     */
    public function getOrCreatePaymentLink()
    {
        // If payment link already exists and is valid, return it
        if (!empty($this->stripe_url) && !empty($this->stripe_id)) {
            Log::info('✅ Using existing payment link', [
                'negotiation_id' => $this->id,
                'stripe_url' => $this->stripe_url
            ]);
            
            return [
                'stripe_url' => $this->stripe_url,
                'stripe_id' => $this->stripe_id,
                'payment_status' => $this->payment_status,
                'already_existed' => true
            ];
        }

        // If no payment link exists, generate one
        try {
            $this->generateStripePaymentLink();
            
            return [
                'stripe_url' => $this->stripe_url,
                'stripe_id' => $this->stripe_id,
                'payment_status' => $this->payment_status,
                'already_existed' => false
            ];
        } catch (\Exception $e) {
            Log::error('❌ Failed to create payment link', [
                'negotiation_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Sync payment status from Stripe by checking all sessions for the payment link
     * This should be called when checking if payment is completed
     */
    public function syncPaymentStatusFromStripe()
    {
        // Only check if payment link exists and status is unpaid
        if (!$this->stripe_id || $this->payment_status === 'paid') {
            return $this->payment_status;
        }

        try {
            $stripe = new \Stripe\StripeClient(env('STRIPE_KEY'));
            
            // Get all checkout sessions for this payment link
            $sessions = $stripe->checkout->sessions->all([
                'payment_link' => $this->stripe_id,
                'limit' => 10
            ]);

            // Check if any session is paid
            foreach ($sessions->data as $session) {
                if ($session->payment_status === 'paid' && $session->status === 'complete') {
                    // Update local record
                    $this->payment_status = 'paid';
                    $this->stripe_session_id = $session->id;
                    $this->save();
                    
                    Log::info('✅ Payment status synced from Stripe', [
                        'negotiation_id' => $this->id,
                        'session_id' => $session->id,
                        'amount' => $session->amount_total
                    ]);
                    
                    return 'paid';
                }
            }
            
            // Still unpaid
            return 'unpaid';
            
        } catch (\Exception $e) {
            Log::error('Failed to sync payment status from Stripe', [
                'negotiation_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            
            // Return current status on error
            return $this->payment_status;
        }
    }
}
