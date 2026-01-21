<?php

namespace App\Models;

use Carbon\Carbon;
use Encore\Admin\Auth\Database\Administrator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class TripBooking extends Model
{
    use HasFactory;

    // Payment Status Constants
    const PAYMENT_STATUS_PENDING = 'Pending';
    const PAYMENT_STATUS_PAID = 'Paid';
    const PAYMENT_STATUS_FAILED = 'Failed';
    const PAYMENT_STATUS_CANCELLED = 'Cancelled';
    const PAYMENT_STATUS_REFUNDED = 'Refunded';

    // Booking Status Constants
    const STATUS_PENDING = 'Pending';
    const STATUS_RESERVED = 'Reserved';
    const STATUS_CANCELED = 'Canceled';
    const STATUS_COMPLETED = 'Completed';

    // Stripe Payment Status
    const STRIPE_PAID_YES = 'Yes';
    const STRIPE_PAID_NO = 'No';

    protected $fillable = [
        'trip_id',
        'customer_id',
        'driver_id',
        'start_stage_id',
        'end_stage_id',
        'status',
        'payment_status',
        'start_time',
        'end_time',
        'slot_count',
        'price',
        'customer_note',
        'driver_notes',
        'start_stage_text',
        'end_stage_text',
        'trip_text',
        'customer_text',
        'driver_text',
        'stripe_id',
        'stripe_url',
        'stripe_product_id',
        'stripe_price_id',
        'stripe_paid',
        'payment_completed_at',
        'payment_failure_reason',
    ];

    protected $casts = [
        'payment_completed_at' => 'datetime',
    ];

    //boot
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            //can't create a trip while having another trip in pending status
            return self::prepare($model);
            $pending_trips = TripBooking::where([
                'status' => 'Pending',
                'customer_id' => $model->customer_id
            ])->first();
            if ($pending_trips) {
                throw new \Exception("You can't create a trip while having another trip in pending status.");
                return false;
            }
            return self::prepare($model);
        });
        //updating
        static::updating(function ($model) {
            return self::prepare($model);
        });
        //created
        static::created(function ($model) {
            //send notification to the driver
            $driver = Administrator::find($model->driver_id);
            if ($driver) {
                Utils::send_message(
                    $driver->phone_number,
                    "RIDESAHRE! You have a new trip booking request. Open the app to view it."
                );
            }
        });
    }
    //prepare
    public static function prepare($model)
    {
        //set the trip text
        $trip = Trip::find($model->trip_id);
        if ($trip) {
            $model->trip_text = $trip->details;
        }
        //set the customer text
        $customer = Administrator::find($model->customer_id);
        if ($customer) {
            $model->customer_text = $customer->name;
        }
        //set the start stage text
        $model->start_stage_text = $trip->start_name;
        //set the end stage text
        $model->end_stage_text = $trip->end_name;
        

        //scheduled_start_time
        try {
            $model->start_time = Carbon::parse($model->start_time);
        } catch (\Exception $e) {
            $model->start_time = $model->start_time;
        }

        return $model;
    }

    //getter for trip_text
    public function getTripTextAttribute()
    {
        $trip = Trip::find($this->trip_id);
        if ($trip) {
            return json_encode($trip);
        }
        return "";
    }

    //getter for customer_text
    public function getCustomerTextAttribute()
    {
        $customer = Administrator::find($this->customer_id);
        if ($customer) {
            return $customer->first_name . " " . $customer->last_name;
        }
        return "";
    }
    //getter for driver_text
    public function getDriverTextAttribute()
    {
        if ($this->trip == null) {
            $this->delete();
            return "";
        }
        $driver = Administrator::find($this->trip->driver_id);
        if ($driver) {
            return $driver->first_name . " " . $driver->last_name;
        }
        return "-";
    }

    //getter for driver_contact
    public function getDriverContactAttribute()
    {
        if ($this->trip == null) {
            $this->delete();
            return "";
        }
        $driver = Administrator::find($this->trip->driver_id);
        if ($driver) {
            return $driver->phone_number;
        }
        $this->delete();
        return "-";
    }
    //getter for customer_contact
    public function getCustomerContactAttribute()
    {
        $customer = Administrator::find($this->customer_id);
        if ($customer) {
            return $customer->phone_number;
        }
        return "-";
    }

    //appends 
    protected $appends = [
        'trip_text',
        'customer_text',
        'customer_contact',
        'driver_text',
        'driver_contact'
    ];

    //customer relationship
    public function customer()
    {
        return $this->belongsTo(Administrator::class, 'customer_id');
    }
    //driver relationship
    public function driver()
    {
        return $this->belongsTo(Administrator::class, 'driver_id');
    }
    //trip relationship
    public function trip()
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    /**
     * ========================================
     * PAYMENT STATUS VALIDATION & HELPERS
     * ========================================
     */

    /**
     * Check if payment is required for this booking
     */
    public function requiresPayment(): bool
    {
        return $this->status === self::STATUS_PENDING && 
               $this->price > 0 &&
               !$this->isPaid();
    }

    /**
     * Check if booking is fully paid
     */
    public function isPaid(): bool
    {
        return $this->stripe_paid === self::STRIPE_PAID_YES || 
               strtolower($this->payment_status) === strtolower(self::PAYMENT_STATUS_PAID);
    }

    /**
     * Check if payment is pending
     */
    public function isPaymentPending(): bool
    {
        return strtolower($this->payment_status) === strtolower(self::PAYMENT_STATUS_PENDING) &&
               $this->stripe_paid !== self::STRIPE_PAID_YES;
    }

    /**
     * Check if payment has valid link
     */
    public function hasValidPaymentLink(): bool
    {
        return !empty($this->stripe_url) && 
               !empty($this->stripe_id) &&
               $this->stripe_paid !== self::STRIPE_PAID_YES;
    }

    /**
     * Check if payment can be retried
     */
    public function canRetryPayment(): bool
    {
        return $this->requiresPayment() || 
               strtolower($this->payment_status) === strtolower(self::PAYMENT_STATUS_FAILED);
    }

    /**
     * Mark payment as completed and update booking status
     */
    public function markAsPaid(string $stripePaymentId = null): bool
    {
        try {
            $this->payment_status = self::PAYMENT_STATUS_PAID;
            $this->stripe_paid = self::STRIPE_PAID_YES;
            $this->status = self::STATUS_RESERVED; // Auto-reserve seat when paid
            
            if (!$this->payment_completed_at) {
                $this->payment_completed_at = now();
            }

            if ($stripePaymentId) {
                $this->stripe_id = $stripePaymentId;
            }

            $saved = $this->save();

            if ($saved) {
                Log::info('💰 Trip booking marked as paid', [
                    'booking_id' => $this->id,
                    'trip_id' => $this->trip_id,
                    'customer_id' => $this->customer_id,
                    'driver_id' => $this->driver_id,
                    'amount' => $this->price,
                    'stripe_id' => $this->stripe_id,
                ]);

                // Distribute payment to driver (90%) and company (10%)
                $this->distributePayment();

                // Send notification to driver
                $this->notifyDriver();
            }

            return $saved;
        } catch (\Exception $e) {
            Log::error('❌ Failed to mark booking payment as paid', [
                'booking_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Distribute payment: 90% to driver, 10% to company
     */
    public function distributePayment(): bool
    {
        try {
            // Check if payment already distributed
            if ($this->hasPaymentBeenDistributed()) {
                Log::info('ℹ️ Booking payment already distributed, skipping', [
                    'booking_id' => $this->id,
                ]);
                return true;
            }

            // Validate price
            if (!$this->price || $this->price <= 0) {
                Log::warning('⚠️ Cannot distribute payment: invalid price', [
                    'booking_id' => $this->id,
                    'price' => $this->price,
                ]);
                return false;
            }

            // Get driver from trip
            $trip = $this->trip;
            if (!$trip) {
                Log::error('❌ Trip not found for payment distribution', [
                    'booking_id' => $this->id,
                    'trip_id' => $this->trip_id,
                ]);
                return false;
            }

            $driver = Administrator::find($trip->driver_id);
            if (!$driver) {
                Log::error('❌ Driver not found for payment distribution', [
                    'booking_id' => $this->id,
                    'driver_id' => $trip->driver_id,
                ]);
                return false;
            }

            return \DB::transaction(function () use ($driver) {
                // Calculate amounts (90/10 split)
                // Price is stored in cents for Stripe compatibility
                $totalAmountCents = $this->price;
                $driverAmountCents = round($totalAmountCents * 0.90);
                $companyAmountCents = round($totalAmountCents * 0.10);

                Log::info('💸 Distributing booking payment', [
                    'booking_id' => $this->id,
                    'total_cents' => $totalAmountCents,
                    'driver_cents' => $driverAmountCents,
                    'company_cents' => $companyAmountCents,
                ]);

                // Get or create driver wallet
                $driverWallet = UserWallet::firstOrCreate(
                    ['user_id' => $driver->id],
                    ['wallet_balance' => 0, 'total_earnings' => 0]
                );

                // Get or create company wallet
                $companyWallet = UserWallet::firstOrCreate(
                    ['user_id' => 1],
                    ['wallet_balance' => 0, 'total_earnings' => 0]
                );

                // Create driver transaction
                $driverTransaction = $this->createDriverTransaction($driverAmountCents, $driverWallet, $driver->id);

                // Create company transaction
                $companyTransaction = $this->createCompanyTransaction($companyAmountCents, $companyWallet, $driver->id);

                // Update wallets
                $driverWallet->wallet_balance += $driverAmountCents;
                $driverWallet->total_earnings += $driverAmountCents;
                $driverWallet->save();

                $companyWallet->wallet_balance += $companyAmountCents;
                $companyWallet->total_earnings += $companyAmountCents;
                $companyWallet->save();

                Log::info('✅ Booking payment distributed successfully', [
                    'booking_id' => $this->id,
                    'driver_new_balance' => $driverWallet->wallet_balance,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('❌ Booking payment distribution failed', [
                'booking_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create driver earning transaction
     */
    private function createDriverTransaction(int $amountCents, UserWallet $wallet, int $driverId): Transaction
    {
        $balanceBefore = $wallet->wallet_balance;
        $balanceAfter = $balanceBefore + $amountCents;
        $reference = 'TXN-RIDE-' . time() . '-' . $this->id . '-DRIVER';

        return Transaction::create([
            'user_id' => $driverId,
            'user_type' => 'driver',
            'payment_id' => null,
            'type' => 'credit',
            'category' => 'rideshare_earning',
            'amount' => $amountCents,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference' => $reference,
            'description' => "Rideshare earning from booking #{$this->id} (90%)",
            'status' => 'completed',
            'related_user_id' => $this->customer_id,
            'trip_booking_id' => $this->id,
            'metadata' => json_encode([
                'payment_type' => 'rideshare_payment',
                'percentage' => 90,
                'total_amount' => $this->price,
                'trip_id' => $this->trip_id,
            ]),
        ]);
    }

    /**
     * Create company commission transaction
     */
    private function createCompanyTransaction(int $amountCents, UserWallet $wallet, int $driverId): Transaction
    {
        $balanceBefore = $wallet->wallet_balance;
        $balanceAfter = $balanceBefore + $amountCents;
        $reference = 'TXN-RIDE-' . time() . '-' . $this->id . '-COMPANY';

        return Transaction::create([
            'user_id' => 1,
            'user_type' => 'driver',
            'payment_id' => null,
            'type' => 'credit',
            'category' => 'rideshare_fee',
            'amount' => $amountCents,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference' => $reference,
            'description' => "Rideshare service fee from booking #{$this->id} (10% commission)",
            'status' => 'completed',
            'related_user_id' => $this->customer_id,
            'trip_booking_id' => $this->id,
            'metadata' => json_encode([
                'payment_type' => 'rideshare_fee',
                'percentage' => 10,
                'total_amount' => $this->price,
                'trip_id' => $this->trip_id,
                'driver_id' => $driverId,
            ]),
        ]);
    }

    /**
     * Check if payment has already been distributed
     */
    private function hasPaymentBeenDistributed(): bool
    {
        $existingTransactions = Transaction::where('trip_booking_id', $this->id)
            ->whereIn('category', ['rideshare_earning', 'rideshare_fee'])
            ->count();

        return $existingTransactions >= 2;
    }

    /**
     * Send notification to driver about successful booking
     */
    private function notifyDriver(): void
    {
        try {
            $trip = $this->trip;
            if (!$trip) return;

            $driver = Administrator::find($trip->driver_id);
            if (!$driver || empty($driver->phone_number)) return;

            $customer = Administrator::find($this->customer_id);
            $customerName = $customer ? $customer->name : 'A customer';

            Utils::send_message(
                $driver->phone_number,
                "NEGORIDE! {$customerName} has booked {$this->slot_count} seat(s) for your trip. Payment confirmed!"
            );
        } catch (\Exception $e) {
            Log::warning('Failed to send booking notification to driver: ' . $e->getMessage());
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
                Log::warning('⚠️ Booking payment marked as failed', [
                    'booking_id' => $this->id,
                    'reason' => $reason,
                ]);
            }

            return $saved;
        } catch (\Exception $e) {
            Log::error('❌ Failed to mark booking payment as failed', [
                'booking_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * ========================================
     * STRIPE PAYMENT LINK CREATION
     * ========================================
     */

    /**
     * Create Stripe payment link for this booking
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
                Log::info('ℹ️ Booking payment link already exists', [
                    'booking_id' => $this->id,
                    'stripe_url' => $this->stripe_url,
                ]);
                return;
            }
            
            // Validate price
            if (!$this->price || $this->price <= 0) {
                throw new \Exception("No price set for booking #{$this->id}");
            }
            
            // Check if already paid
            if ($this->isPaid()) {
                throw new \Exception("Payment already completed for booking #{$this->id}");
            }
            
            // Get customer and trip info
            $customer = $this->customer;
            $customer_name = $customer ? $customer->name : 'Customer #' . $this->customer_id;
            
            $trip = $this->trip;
            if (!$trip) {
                throw new \Exception("Trip not found for booking #{$this->id}");
            }
            
            $driver = Administrator::find($trip->driver_id);
            $driver_name = $driver ? $driver->name : 'Driver';
        
            Log::info('🔄 Creating Stripe payment link for booking', [
                'booking_id' => $this->id,
                'amount' => $this->price,
                'customer' => $customer_name,
            ]);

            // Amount in cents (price is already in cents from booking creation)
            $amount_cents = intval($this->price);
            
            // Stripe minimum is 50 cents
            if ($amount_cents < 50) {
                throw new \Exception("Payment amount too small: $amount_cents cents (minimum 50 cents)");
            }
            
            // Product name
            $product_name = "Rideshare Booking #{$this->id} - {$this->slot_count} seat(s)";
            
            // Description
            $description = "Trip from {$trip->start_name} to {$trip->end_name} with {$driver_name}";
            if (strlen($description) > 500) {
                $description = substr($description, 0, 497) . '...';
            }
            
            $stripe = new \Stripe\StripeClient($stripe_key);
            
            // Create Stripe product
            $product = $stripe->products->create([
                'name' => $product_name,
                'description' => $description,
                'metadata' => [
                    'booking_id' => $this->id,
                    'trip_id' => $this->trip_id,
                    'customer_id' => $this->customer_id,
                    'driver_id' => $trip->driver_id,
                    'negoride_source' => 'rideshare_booking',
                    'slot_count' => $this->slot_count,
                ]
            ]);

            Log::info('✅ Stripe product created for booking', ['product_id' => $product->id]);

            // Create price
            $price = $stripe->prices->create([
                'unit_amount' => $amount_cents,
                'currency' => 'cad',
                'product' => $product->id,
                'metadata' => [
                    'booking_id' => $this->id,
                    'price_cents' => $amount_cents
                ]
            ]);

            Log::info('✅ Stripe price created for booking', [
                'price_id' => $price->id,
                'amount_cents' => $amount_cents
            ]);

            // Create payment link
            $paymentLink = $stripe->paymentLinks->create([
                'line_items' => [
                    [
                        'price' => $price->id,
                        'quantity' => 1,
                    ]
                ],
                'metadata' => [
                    'booking_id' => $this->id,
                    'trip_id' => $this->trip_id,
                    'customer_id' => $this->customer_id,
                    'driver_id' => $trip->driver_id,
                    'slot_count' => $this->slot_count,
                    'negoride_source' => 'rideshare_booking',
                ],
                'after_completion' => [
                    'type' => 'redirect',
                    'redirect' => [
                        'url' => env('APP_URL', 'http://localhost:8888/negoride-canada-api') . '/rideshare-payment-success?booking_id=' . $this->id
                    ]
                ]
            ]);
            
            // Update booking with payment link details
            $this->stripe_id = $paymentLink->id;
            $this->stripe_url = $paymentLink->url;
            $this->stripe_product_id = $product->id;
            $this->stripe_price_id = $price->id;
            $this->stripe_paid = self::STRIPE_PAID_NO;
            $this->payment_status = self::PAYMENT_STATUS_PENDING;
            
            $this->save();
            
            Log::info('✅ Payment link created for booking', [
                'booking_id' => $this->id,
                'stripe_url' => $this->stripe_url,
                'amount_cents' => $amount_cents,
            ]);
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('❌ Stripe API error for booking', [
                'booking_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            
            $this->markPaymentFailed('Stripe API error: ' . $e->getMessage());
            throw new \Exception("Stripe error: " . $e->getMessage());
            
        } catch (\Exception $e) {
            Log::error('❌ Payment link creation failed for booking', [
                'booking_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            
            $this->markPaymentFailed('System error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get or create payment link
     */
    public function getOrCreatePaymentLink()
    {
        // If payment link already exists, return it
        if (!empty($this->stripe_url) && !empty($this->stripe_id)) {
            Log::info('✅ Using existing payment link for booking', [
                'booking_id' => $this->id,
                'stripe_url' => $this->stripe_url
            ]);
            
            return [
                'stripe_url' => $this->stripe_url,
                'stripe_id' => $this->stripe_id,
                'payment_status' => $this->payment_status,
                'already_existed' => true
            ];
        }

        // Create new payment link
        $this->create_payment_link();
        
        return [
            'stripe_url' => $this->stripe_url,
            'stripe_id' => $this->stripe_id,
            'payment_status' => $this->payment_status,
            'already_existed' => false
        ];
    }
}
