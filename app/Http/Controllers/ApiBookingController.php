<?php

namespace App\Http\Controllers;

use App\Models\ScheduledBooking;
use App\Models\Utils;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use Encore\Admin\Auth\Database\Administrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ApiBookingController extends Controller
{
    use ApiResponser;

    // ============================================================
    // AUTH HELPER (same fallback chain as ApiNegotiationController)
    // ============================================================

    protected function getAuthUser()
    {
        // 1. Middleware's setUserResolver
        $user = request()->user();
        if ($user) {
            Log::debug('BookingController: Auth via request()->user()', ['id' => $user->id]);
            return $user;
        }

        // 2. Middleware's merge('auth_user')
        $user = request()->get('auth_user');
        if ($user) {
            Log::debug('BookingController: Auth via auth_user merge', ['id' => $user->id]);
            return $user;
        }

        // 3. API guard
        $user = auth('api')->user();
        if ($user) {
            Log::debug('BookingController: Auth via api guard', ['id' => $user->id]);
            return $user;
        }

        // 4. Direct JWT parse
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if ($user) {
                Log::debug('BookingController: Auth via JWTAuth::parseToken', ['id' => $user->id]);
                return $user;
            }
        } catch (\Exception $e) {
            Log::debug('BookingController: JWTAuth::parseToken failed: ' . $e->getMessage());
        }

        // 5. Manual header extraction
        try {
            $headers   = function_exists('getallheaders') ? getallheaders() : [];
            $authHeader = '';
            if (isset($headers['Authorization']))       $authHeader = $headers['Authorization'];
            elseif (isset($headers['authorization']))   $authHeader = $headers['authorization'];
            elseif (isset($headers['token']))            $authHeader = 'Bearer ' . $headers['token'];
            elseif (isset($headers['tok']))              $authHeader = 'Bearer ' . $headers['tok'];

            if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                $token = substr($authHeader, 7);
                $user  = JWTAuth::setToken($token)->authenticate();
                if ($user) {
                    Log::debug('BookingController: Auth via manual header', ['id' => $user->id]);
                    return $user;
                }
            }
        } catch (\Exception $e) {
            Log::warning('BookingController: Manual JWT auth failed: ' . $e->getMessage());
        }

        // 6. user_id fallback (sent by Flutter in request body)
        $userId = request()->input('user_id') ?? request()->get('user_id') ?? request()->header('user_id');
        if ($userId) {
            Log::info('BookingController: Using user_id fallback for authentication', ['user_id' => $userId]);
            $user = Administrator::find($userId);
            if ($user) return $user;
        }

        Log::error('BookingController: All auth methods failed');
        return null;
    }

    // ============================================================
    // GET /api/bookings — list bookings for the authenticated user
    // ============================================================

    public function index(Request $r)
    {
        $user = $this->getAuthUser();
        if (!$user) return $this->error('User not authenticated.');

        $query = ScheduledBooking::with(['customer', 'driver']);

        // Admin (user_id=1) sees ALL bookings; others see only their own
        if ($user->id !== 1) {
            $query->where(function ($q) use ($user) {
                $q->where('customer_id', $user->id)
                  ->orWhere('driver_id', $user->id);
            });
        }

        // Optional status filter
        if ($r->has('status') && $r->status) {
            $query->where('status', $r->status);
        }

        $bookings = $query->orderBy('created_at', 'desc')->get();

        return $this->success($bookings, 'Bookings retrieved.');
    }

    // ============================================================
    // POST /api/bookings — customer creates a booking
    // ============================================================

    public function create(Request $r)
    {
        $user = $this->getAuthUser();
        if (!$user) return $this->error('User not authenticated.');

        $validator = Validator::make($r->all(), [
            'service_type'             => 'required|string|max:100',
            'automobile_type'          => 'nullable|string|max:100',
            'pickup_lat'               => 'required|numeric',
            'pickup_lng'               => 'required|numeric',
            'pickup_place_name'        => 'nullable|string|max:300',
            'pickup_address'           => 'required|string|max:500',
            'pickup_description'       => 'nullable|string|max:1000',
            'destination_lat'          => 'required|numeric',
            'destination_lng'          => 'required|numeric',
            'destination_place_name'   => 'nullable|string|max:300',
            'destination_address'      => 'required|string|max:500',
            'destination_description'  => 'nullable|string|max:1000',
            'passengers'               => 'required|integer|min:1|max:10',
            'luggage'                  => 'required|integer|min:0|max:20',
            'luggage_weight_lbs'       => 'nullable|integer|min:0|max:9999',
            'luggage_description'      => 'nullable|string|max:1000',
            'message'                  => 'nullable|string|max:1000',
            'scheduled_at'             => 'required|date|after:now',
            'customer_proposed_price'  => 'required|integer|min:50',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed: ' . implode(', ', $validator->errors()->all()));
        }

        try {
            $booking = ScheduledBooking::create([
                'customer_id'             => $user->id,
                'service_type'            => $r->service_type,
                'automobile_type'         => $r->automobile_type,
                'pickup_lat'              => $r->pickup_lat,
                'pickup_lng'              => $r->pickup_lng,
                'pickup_place_name'       => $r->pickup_place_name,
                'pickup_address'          => $r->pickup_address,
                'pickup_description'      => $r->pickup_description,
                'destination_lat'         => $r->destination_lat,
                'destination_lng'         => $r->destination_lng,
                'destination_place_name'  => $r->destination_place_name,
                'destination_address'     => $r->destination_address,
                'destination_description' => $r->destination_description,
                'passengers'              => $r->passengers,
                'luggage'                 => $r->luggage,
                'luggage_weight_lbs'      => intval($r->luggage_weight_lbs ?? 0),
                'luggage_description'     => $r->luggage_description,
                'message'                 => $r->message,
                'scheduled_at'            => Carbon::parse($r->scheduled_at),
                'customer_proposed_price' => intval($r->customer_proposed_price),
                'status'                  => ScheduledBooking::STATUS_PENDING,
                'payment_status'          => ScheduledBooking::PAYMENT_UNPAID,
            ]);

            // Notify admin (user_id = 1) of new booking
            $admin = Administrator::find(1);
            if ($admin && !empty($admin->phone_number)) {
                try {
                    Utils::send_message(
                        $admin->phone_number,
                        "NEGORIDE! New scheduled booking #{$booking->id} from {$user->name}. Service: {$booking->service_type}. Date: " .
                        Carbon::parse($booking->scheduled_at)->format('M j, Y g:i A') .
                        ". Open admin panel to assign a driver."
                    );
                } catch (\Throwable $e) {
                    Log::warning('Failed to notify admin of new booking: ' . $e->getMessage());
                }
            }

            Log::info('📅 Scheduled booking created', [
                'booking_id'  => $booking->id,
                'customer_id' => $user->id,
                'service'     => $booking->service_type,
            ]);

            return $this->success($booking->fresh(['customer', 'driver']), 'Booking created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create booking', ['error' => $e->getMessage()]);
            return $this->error('Failed to create booking: ' . $e->getMessage());
        }
    }

    // ============================================================
    // GET /api/bookings/{id} — show a single booking
    // ============================================================

    public function show($id)
    {
        $user = $this->getAuthUser();
        if (!$user) return $this->error('User not authenticated.');

        $booking = ScheduledBooking::with(['customer', 'driver'])->find($id);
        if (!$booking) return $this->error('Booking not found.', 404);

        // Only customer, driver, or admin can see
        if ($booking->customer_id !== $user->id &&
            $booking->driver_id   !== $user->id &&
            $user->id             !== 1) {
            return $this->error('Unauthorized.', 403);
        }

        return $this->success($booking, 'Booking retrieved.');
    }

    // ============================================================
    // POST /api/bookings/{id}/cancel — customer cancels
    // ============================================================

    public function cancel($id, Request $r)
    {
        $user = $this->getAuthUser();
        if (!$user) return $this->error('User not authenticated.');

        $booking = ScheduledBooking::find($id);
        if (!$booking) return $this->error('Booking not found.', 404);

        if ($booking->customer_id !== $user->id && $user->id !== 1) {
            return $this->error('Unauthorized.', 403);
        }

        if (!$booking->isCancellable()) {
            return $this->error('This booking cannot be cancelled in its current state (' . $booking->status . ').');
        }

        $booking->status              = ScheduledBooking::STATUS_CANCELLED;
        $booking->cancelled_at        = now();
        $booking->cancellation_reason = $r->reason ?? 'Cancelled by customer';
        $booking->save();

        return $this->success($booking, 'Booking cancelled.');
    }

    // ============================================================
    // POST /api/bookings/{id}/propose-price — driver counter-offer
    // ============================================================

    public function proposePrice($id, Request $r)
    {
        $user = $this->getAuthUser();
        if (!$user) return $this->error('User not authenticated.');

        $validator = Validator::make($r->all(), [
            'price' => 'required|integer|min:50',
        ]);
        if ($validator->fails()) {
            return $this->error('Validation failed: ' . implode(', ', $validator->errors()->all()));
        }

        $booking = ScheduledBooking::with('customer')->find($id);
        if (!$booking) return $this->error('Booking not found.', 404);

        if ($booking->driver_id !== $user->id && $user->id !== 1) {
            return $this->error('Only the assigned driver can propose a price.', 403);
        }

        if (!in_array($booking->status, [
            ScheduledBooking::STATUS_DRIVER_ASSIGNED,
            ScheduledBooking::STATUS_PRICE_NEGOTIATING,
        ])) {
            return $this->error('Cannot propose price in current status: ' . $booking->status);
        }

        $booking->driver_proposed_price = intval($r->price);
        $booking->status                = ScheduledBooking::STATUS_PRICE_NEGOTIATING;
        $booking->save();

        // Notify customer
        $customer = $booking->customer;
        if ($customer && !empty($customer->phone_number)) {
            try {
                Utils::send_message(
                    $customer->phone_number,
                    "NEGORIDE! Your driver proposed a price of C$" .
                    number_format($r->price / 100, 2) .
                    " for booking #{$booking->id}. Open the app to respond."
                );
            } catch (\Throwable $e) {
                Log::warning('SMS failed: ' . $e->getMessage());
            }
        }

        return $this->success($booking, 'Price proposed.');
    }

    // ============================================================
    // POST /api/bookings/{id}/accept-price — customer accepts driver's price
    // ============================================================

    public function acceptPrice($id, Request $r)
    {
        $user = $this->getAuthUser();
        if (!$user) return $this->error('User not authenticated.');

        $booking = ScheduledBooking::with('driver')->find($id);
        if (!$booking) return $this->error('Booking not found.', 404);

        if ($booking->customer_id !== $user->id) {
            return $this->error('Only the customer can accept the price.', 403);
        }

        if ($booking->status !== ScheduledBooking::STATUS_PRICE_NEGOTIATING ||
            !$booking->driver_proposed_price) {
            return $this->error('No pending driver price to accept.');
        }

        $booking->agreed_price = $booking->driver_proposed_price;
        $booking->status       = ScheduledBooking::STATUS_PRICE_ACCEPTED;
        $booking->save();

        // Generate Stripe payment link
        try {
            $booking->create_payment_link();
        } catch (\Exception $e) {
            Log::warning('Payment link generation failed after price accept: ' . $e->getMessage());
        }

        return $this->success($booking->fresh(), 'Price accepted. Payment link generated.');
    }

    // ============================================================
    // POST /api/bookings/{id}/accept-original-price — driver accepts customer's price
    // ============================================================

    public function acceptOriginalPrice($id, Request $r)
    {
        $user = $this->getAuthUser();
        if (!$user) return $this->error('User not authenticated.');

        $booking = ScheduledBooking::with('customer')->find($id);
        if (!$booking) return $this->error('Booking not found.', 404);

        if ($booking->driver_id !== $user->id && $user->id !== 1) {
            return $this->error('Only the assigned driver can accept the customer price.', 403);
        }

        if (!in_array($booking->status, [
            ScheduledBooking::STATUS_DRIVER_ASSIGNED,
            ScheduledBooking::STATUS_PRICE_NEGOTIATING,
        ])) {
            return $this->error('Cannot accept price in current status: ' . $booking->status);
        }

        $booking->agreed_price = $booking->customer_proposed_price;
        $booking->status       = ScheduledBooking::STATUS_PRICE_ACCEPTED;
        $booking->save();

        // Generate Stripe payment link
        try {
            $booking->create_payment_link();
        } catch (\Exception $e) {
            Log::warning('Payment link generation failed after driver accept: ' . $e->getMessage());
        }

        // Notify customer
        $customer = $booking->customer;
        if ($customer && !empty($customer->phone_number)) {
            try {
                Utils::send_message(
                    $customer->phone_number,
                    "NEGORIDE! Your driver accepted your price of C$" .
                    number_format($booking->agreed_price / 100, 2) .
                    " for booking #{$booking->id}. Please pay to confirm."
                );
            } catch (\Throwable $e) {
                Log::warning('SMS failed: ' . $e->getMessage());
            }
        }

        return $this->success($booking->fresh(), 'Customer price accepted. Payment link generated.');
    }

    // ============================================================
    // POST /api/bookings/{id}/assign-driver — admin assigns driver
    // ============================================================

    public function assignDriver($id, Request $r)
    {
        $user = $this->getAuthUser();
        if (!$user) return $this->error('User not authenticated.');

        // Only admin (user_id=1) can assign drivers
        if ($user->id !== 1) {
            return $this->error('Unauthorized. Admin only.', 403);
        }

        $validator = Validator::make($r->all(), [
            'driver_id' => 'required|integer|exists:admin_users,id',
        ]);
        if ($validator->fails()) {
            return $this->error('Validation failed: ' . implode(', ', $validator->errors()->all()));
        }

        $booking = ScheduledBooking::with('customer')->find($id);
        if (!$booking) return $this->error('Booking not found.', 404);

        if (!in_array($booking->status, [
            ScheduledBooking::STATUS_PENDING,
            ScheduledBooking::STATUS_DRIVER_ASSIGNED,
        ])) {
            return $this->error('Cannot assign driver in current status: ' . $booking->status);
        }

        $driver = Administrator::find($r->driver_id);
        if (!$driver) return $this->error('Driver not found.', 404);

        $booking->driver_id   = $driver->id;
        $booking->assigned_by = $user->id;
        $booking->assigned_at = now();
        $booking->status      = ScheduledBooking::STATUS_DRIVER_ASSIGNED;
        $booking->save();

        // Notify driver
        if (!empty($driver->phone_number)) {
            try {
                Utils::send_message(
                    $driver->phone_number,
                    "NEGORIDE! You have been assigned to booking #{$booking->id}. Service: {$booking->service_type}. Date: " .
                    Carbon::parse($booking->scheduled_at)->format('M j, Y g:i A') .
                    ". Customer price: C$" . number_format($booking->customer_proposed_price / 100, 2) .
                    ". Open the app to respond."
                );
            } catch (\Throwable $e) {
                Log::warning('SMS to driver failed: ' . $e->getMessage());
            }
        }

        return $this->success($booking->fresh(['customer', 'driver']), 'Driver assigned.');
    }

    // ============================================================
    // POST /api/bookings/{id}/start — driver starts trip
    // ============================================================

    public function startTrip($id, Request $r)
    {
        $user = $this->getAuthUser();
        if (!$user) return $this->error('User not authenticated.');

        $booking = ScheduledBooking::find($id);
        if (!$booking) return $this->error('Booking not found.', 404);

        if ($booking->driver_id !== $user->id && $user->id !== 1) {
            return $this->error('Only the assigned driver can start this trip.', 403);
        }

        if ($booking->status !== ScheduledBooking::STATUS_CONFIRMED) {
            return $this->error('Booking must be confirmed before starting. Current status: ' . $booking->status);
        }

        if (!$booking->isPaid()) {
            return $this->error('Payment has not been completed. Cannot start trip.');
        }

        $booking->status     = ScheduledBooking::STATUS_IN_PROGRESS;
        $booking->started_at = now();
        $booking->save();

        return $this->success($booking, 'Trip started.');
    }

    // ============================================================
    // POST /api/bookings/{id}/complete — driver completes trip
    // ============================================================

    public function completeTrip($id, Request $r)
    {
        $user = $this->getAuthUser();
        if (!$user) return $this->error('User not authenticated.');

        $booking = ScheduledBooking::find($id);
        if (!$booking) return $this->error('Booking not found.', 404);

        if ($booking->driver_id !== $user->id && $user->id !== 1) {
            return $this->error('Only the assigned driver can complete this trip.', 403);
        }

        if ($booking->status !== ScheduledBooking::STATUS_IN_PROGRESS) {
            return $this->error('Trip is not in progress. Current status: ' . $booking->status);
        }

        $booking->status       = ScheduledBooking::STATUS_COMPLETED;
        $booking->completed_at = now();
        if ($r->driver_notes) {
            $booking->driver_notes = $r->driver_notes;
        }
        $booking->save();

        Log::info('✅ Booking trip completed', ['booking_id' => $booking->id]);

        return $this->success($booking, 'Trip completed successfully.');
    }

    // ============================================================
    // POST /api/bookings/{id}/refresh-payment — get/refresh Stripe link
    // ============================================================

    public function refreshPayment($id, Request $r)
    {
        $user = $this->getAuthUser();
        if (!$user) return $this->error('User not authenticated.');

        $booking = ScheduledBooking::find($id);
        if (!$booking) return $this->error('Booking not found.', 404);

        if ($booking->customer_id !== $user->id && $user->id !== 1) {
            return $this->error('Only the customer can request payment.', 403);
        }

        if ($booking->isPaid()) {
            return $this->success($booking, 'Payment already completed.');
        }

        if (!in_array($booking->status, [
            ScheduledBooking::STATUS_PRICE_ACCEPTED,
            ScheduledBooking::STATUS_PAYMENT_PENDING,
        ])) {
            return $this->error('Booking is not ready for payment. Status: ' . $booking->status);
        }

        try {
            // Reset old Stripe fields so a fresh link is generated
            if (!$booking->hasPaymentLink()) {
                $booking->create_payment_link();
            } else {
                // Reset and regenerate if link exists but not paid
                $booking->stripe_id       = null;
                $booking->stripe_url      = null;
                $booking->stripe_product_id = null;
                $booking->stripe_price_id = null;
                $booking->save();
                $booking->create_payment_link();
            }

            return $this->success($booking->fresh(), 'Payment link refreshed.');
        } catch (\Exception $e) {
            return $this->error('Failed to generate payment link: ' . $e->getMessage());
        }
    }

    // ============================================================
    // POST /api/bookings/{id}/check-payment — verify Stripe payment
    // ============================================================

    public function checkPayment($id, Request $r)
    {
        $user = $this->getAuthUser();
        if (!$user) return $this->error('User not authenticated.');

        $booking = ScheduledBooking::find($id);
        if (!$booking) return $this->error('Booking not found.', 404);

        if ($booking->customer_id !== $user->id && $user->id !== 1) {
            return $this->error('Unauthorized.', 403);
        }

        if ($booking->isPaid()) {
            return $this->success([
                'booking'        => $booking,
                'payment_status' => 'paid',
                'is_paid'        => true,
            ], 'Payment confirmed.');
        }

        $stripeStatus = $booking->syncPaymentStatusFromStripe();

        return $this->success([
            'booking'        => $booking->fresh(),
            'payment_status' => $stripeStatus,
            'is_paid'        => $stripeStatus === ScheduledBooking::PAYMENT_PAID,
        ], 'Payment status checked.');
    }

    // ============================================================
    // POST /api/bookings/{id}/mark-paid — admin manually marks as paid
    // ============================================================

    public function markPaid($id, Request $r)
    {
        $user = $this->getAuthUser();
        if (!$user) return $this->error('User not authenticated.');

        // Only admin (user_id=1) can force-mark as paid
        if ($user->id !== 1) {
            return $this->error('Unauthorized. Admin only.', 403);
        }

        $booking = ScheduledBooking::with(['customer', 'driver'])->find($id);
        if (!$booking) return $this->error('Booking not found.', 404);

        if ($booking->isPaid()) {
            return $this->success($booking, 'Already paid.');
        }

        if (!in_array($booking->status, [
            ScheduledBooking::STATUS_PRICE_ACCEPTED,
            ScheduledBooking::STATUS_PAYMENT_PENDING,
        ])) {
            return $this->error('Cannot mark as paid in current status: ' . $booking->status);
        }

        $result = $booking->markAsPaid();
        if (!$result) {
            return $this->error('Failed to mark booking as paid.');
        }

        return $this->success($booking->fresh(['customer', 'driver']), 'Booking marked as paid and confirmed.');
    }
}
