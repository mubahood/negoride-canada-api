<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ScheduledBooking extends Model
{
    use HasFactory;

    // ============================================================
    // STATUS CONSTANTS
    // ============================================================
    const STATUS_PENDING           = 'pending';
    const STATUS_DRIVER_ASSIGNED   = 'driver_assigned';
    const STATUS_PRICE_NEGOTIATING = 'price_negotiating';
    const STATUS_PRICE_ACCEPTED    = 'price_accepted';
    const STATUS_PAYMENT_PENDING   = 'payment_pending';
    const STATUS_PAYMENT_COMPLETED = 'payment_completed';
    const STATUS_CONFIRMED         = 'confirmed';
    const STATUS_IN_PROGRESS       = 'in_progress';
    const STATUS_COMPLETED         = 'completed';
    const STATUS_CANCELLED         = 'cancelled';

    // ============================================================
    // PAYMENT STATUS CONSTANTS
    // ============================================================
    const PAYMENT_UNPAID   = 'unpaid';
    const PAYMENT_PENDING  = 'pending';
    const PAYMENT_PAID     = 'paid';
    const PAYMENT_FAILED   = 'failed';

    protected $fillable = [
        'customer_id', 'driver_id', 'assigned_by',
        'service_type', 'automobile_type',
        'pickup_lat', 'pickup_lng', 'pickup_place_name', 'pickup_address', 'pickup_description',
        'destination_lat', 'destination_lng', 'destination_place_name', 'destination_address', 'destination_description',
        'passengers', 'luggage', 'luggage_weight_lbs', 'luggage_description', 'message',
        'scheduled_at',
        'customer_proposed_price', 'driver_proposed_price', 'agreed_price',
        'status', 'payment_status',
        'stripe_id', 'stripe_url', 'stripe_product_id', 'stripe_price_id',
        'stripe_paid', 'payment_completed_at',
        'assigned_at', 'confirmed_at', 'started_at', 'completed_at', 'cancelled_at',
        'cancellation_reason', 'driver_notes', 'admin_notes',
    ];

    protected $casts = [
        'pickup_lat'       => 'float',
        'pickup_lng'       => 'float',
        'destination_lat'  => 'float',
        'destination_lng'  => 'float',
        'scheduled_at'     => 'datetime',
        'payment_completed_at' => 'datetime',
        'assigned_at'      => 'datetime',
        'confirmed_at'     => 'datetime',
        'started_at'       => 'datetime',
        'completed_at'     => 'datetime',
        'cancelled_at'     => 'datetime',
        'stripe_paid'      => 'boolean',
    ];

    // ============================================================
    // RELATIONSHIPS
    // ============================================================

    public function customer()
    {
        return $this->belongsTo(\Encore\Admin\Auth\Database\Administrator::class, 'customer_id');
    }

    public function driver()
    {
        return $this->belongsTo(\Encore\Admin\Auth\Database\Administrator::class, 'driver_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(\Encore\Admin\Auth\Database\Administrator::class, 'assigned_by');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'booking_id');
    }

    // ============================================================
    // HELPER METHODS
    // ============================================================

    public function isCancellable(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_DRIVER_ASSIGNED,
            self::STATUS_PRICE_NEGOTIATING,
            self::STATUS_PRICE_ACCEPTED,
            self::STATUS_PAYMENT_PENDING,
        ]);
    }

    public function isNegotiating(): bool
    {
        return $this->status === self::STATUS_PRICE_NEGOTIATING;
    }

    public function canStartTrip(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function requiresPayment(): bool
    {
        return in_array($this->status, [
            self::STATUS_PRICE_ACCEPTED,
            self::STATUS_PAYMENT_PENDING,
        ]) && !$this->isPaid();
    }

    public function isPaid(): bool
    {
        return $this->stripe_paid === true ||
               $this->payment_status === self::PAYMENT_PAID;
    }

    public function hasPaymentLink(): bool
    {
        return !empty($this->stripe_url) && !empty($this->stripe_id);
    }

    // ============================================================
    // STRIPE PAYMENT LINK
    // ============================================================

    public function create_payment_link(): void
    {
        try {
            $stripe_key = env('STRIPE_KEY');
            if (empty($stripe_key)) {
                throw new \Exception("Stripe API key not configured");
            }

            if ($this->hasPaymentLink() && $this->isPaid()) {
                return; // Already paid
            }

            if (!$this->agreed_price || $this->agreed_price <= 0) {
                throw new \Exception("No agreed price set for booking #{$this->id}");
            }

            $amount_cents = intval($this->agreed_price);
            if ($amount_cents < 50) {
                throw new \Exception("Payment amount too small: {$amount_cents} cents");
            }

            $customer = $this->customer;
            $customer_name = $customer ? $customer->name : 'Customer #' . $this->customer_id;

            $product_name = "Scheduled Booking #{$this->id} - {$customer_name}";
            $description  = "Trip from {$this->pickup_address} to {$this->destination_address} on " .
                            $this->scheduled_at->format('M j, Y g:i A');
            if (strlen($description) > 500) {
                $description = substr($description, 0, 497) . '...';
            }

            $stripe = new \Stripe\StripeClient($stripe_key);

            $product = $stripe->products->create([
                'name'        => $product_name,
                'description' => $description,
                'metadata'    => [
                    'booking_id'  => $this->id,
                    'customer_id' => $this->customer_id,
                    'driver_id'   => $this->driver_id,
                ],
            ]);

            $price = $stripe->prices->create([
                'unit_amount' => $amount_cents,
                'currency'    => 'cad',
                'product'     => $product->id,
                'metadata'    => ['booking_id' => $this->id],
            ]);

            $paymentLink = $stripe->paymentLinks->create([
                'line_items' => [['price' => $price->id, 'quantity' => 1]],
                'metadata'   => [
                    'booking_id'  => $this->id,
                    'customer_id' => $this->customer_id,
                    'driver_id'   => $this->driver_id,
                    'agreed_price' => $this->agreed_price,
                ],
                'after_completion' => [
                    'type'     => 'redirect',
                    'redirect' => [
                        'url' => env('APP_URL', 'http://localhost:8888/negoride-canada-api') .
                                 '/payment-success?booking_id=' . $this->id,
                    ],
                ],
            ]);

            $this->stripe_id       = $paymentLink->id;
            $this->stripe_url      = $paymentLink->url;
            $this->stripe_product_id = $product->id;
            $this->stripe_price_id = $price->id;
            $this->stripe_paid     = false;
            $this->payment_status  = self::PAYMENT_PENDING;
            $this->status          = self::STATUS_PAYMENT_PENDING;
            $this->save();

            Log::info('✅ Booking payment link created', [
                'booking_id' => $this->id,
                'stripe_url' => $this->stripe_url,
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Booking payment link creation failed', [
                'booking_id' => $this->id,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Sync payment status from Stripe checkout sessions
     */
    public function syncPaymentStatusFromStripe(): string
    {
        if (!$this->stripe_id || $this->payment_status === self::PAYMENT_PAID) {
            return $this->payment_status ?? self::PAYMENT_UNPAID;
        }

        try {
            $stripe   = new \Stripe\StripeClient(env('STRIPE_KEY'));
            $sessions = $stripe->checkout->sessions->all([
                'payment_link' => $this->stripe_id,
                'limit'        => 10,
            ]);

            foreach ($sessions->data as $session) {
                if ($session->payment_status === 'paid' && $session->status === 'complete') {
                    $this->markAsPaid();
                    return self::PAYMENT_PAID;
                }
            }

            return self::PAYMENT_UNPAID;
        } catch (\Exception $e) {
            Log::error('Failed to sync booking payment from Stripe', [
                'booking_id' => $this->id,
                'error'      => $e->getMessage(),
            ]);
            return $this->payment_status ?? self::PAYMENT_UNPAID;
        }
    }

    /**
     * Mark booking as paid and transition status
     */
    public function markAsPaid(): bool
    {
        try {
            $this->stripe_paid          = true;
            $this->payment_status       = self::PAYMENT_PAID;
            $this->payment_completed_at = now();
            $this->status               = self::STATUS_CONFIRMED;
            $this->confirmed_at         = now();
            $saved = $this->save();

            if ($saved) {
                Log::info('💰 Booking marked as paid', ['booking_id' => $this->id]);
                $this->distributePayment();
            }

            return $saved;
        } catch (\Exception $e) {
            Log::error('❌ Failed to mark booking as paid', [
                'booking_id' => $this->id,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Distribute payment: 90% driver, 10% company
     */
    public function distributePayment(): bool
    {
        try {
            // Idempotency: skip if already distributed
            $existing = Transaction::where('booking_id', $this->id)
                ->whereIn('category', ['ride_earning', 'service_fee'])
                ->count();
            if ($existing >= 2) {
                return true;
            }

            if (!$this->agreed_price || $this->agreed_price <= 0) {
                return false;
            }

            if (!$this->driver_id) {
                Log::warning('Cannot distribute payment: no driver assigned', ['booking_id' => $this->id]);
                return false;
            }

            return \DB::transaction(function () {
                $total          = $this->agreed_price;
                $driverAmount   = round($total * 0.90, 2);
                $companyAmount  = round($total * 0.10, 2);

                $driverWallet  = UserWallet::firstOrCreate(
                    ['user_id' => $this->driver_id],
                    ['wallet_balance' => 0, 'total_earnings' => 0]
                );
                $companyWallet = UserWallet::firstOrCreate(
                    ['user_id' => 1],
                    ['wallet_balance' => 0, 'total_earnings' => 0]
                );

                // Driver transaction
                Transaction::create([
                    'user_id'         => $this->driver_id,
                    'user_type'       => 'driver',
                    'payment_id'      => null,
                    'type'            => 'credit',
                    'category'        => 'ride_earning',
                    'amount'          => $driverAmount,
                    'balance_before'  => $driverWallet->wallet_balance,
                    'balance_after'   => $driverWallet->wallet_balance + $driverAmount,
                    'reference'       => 'BKG-' . time() . '-' . $this->id . '-DRIVER',
                    'description'     => "Scheduled booking #{$this->id} earning (90%)",
                    'status'          => 'completed',
                    'related_user_id' => $this->customer_id,
                    'booking_id'      => $this->id,
                    'metadata'        => json_encode(['payment_type' => 'booking_payment', 'percentage' => 90]),
                ]);

                // Company transaction
                Transaction::create([
                    'user_id'         => 1,
                    'user_type'       => 'driver',
                    'payment_id'      => null,
                    'type'            => 'credit',
                    'category'        => 'service_fee',
                    'amount'          => $companyAmount,
                    'balance_before'  => $companyWallet->wallet_balance,
                    'balance_after'   => $companyWallet->wallet_balance + $companyAmount,
                    'reference'       => 'BKG-' . time() . '-' . $this->id . '-COMPANY',
                    'description'     => "Service fee from booking #{$this->id} (10%)",
                    'status'          => 'completed',
                    'related_user_id' => $this->customer_id,
                    'booking_id'      => $this->id,
                    'metadata'        => json_encode(['payment_type' => 'service_fee', 'percentage' => 10]),
                ]);

                $driverWallet->wallet_balance  += $driverAmount;
                $driverWallet->total_earnings  += $driverAmount;
                $driverWallet->save();

                $companyWallet->wallet_balance += $companyAmount;
                $companyWallet->total_earnings += $companyAmount;
                $companyWallet->save();

                Log::info('✅ Booking payment distributed', ['booking_id' => $this->id]);
                return true;
            });
        } catch (\Exception $e) {
            Log::error('❌ Booking payment distribution failed', [
                'booking_id' => $this->id,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }
    }
}
