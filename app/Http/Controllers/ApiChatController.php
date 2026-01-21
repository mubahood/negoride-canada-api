<?php

namespace App\Http\Controllers;

use App\Models\Chat\ChatHead;
use App\Models\Chat\ChatMessage;
use App\Models\Negotiation;
use App\Models\NegotiationRecord;
use App\Models\Trip;
use App\Models\TripBooking;
use App\Models\User;
use App\Models\Utils;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use Encore\Admin\Auth\Database\Administrator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;
use Tymon\JWTAuth\Facades\JWTAuth;

class ApiChatController extends Controller
{

    use ApiResponser;

    public function negotiation_create(Request $r)
    {

        $customer = auth('api')->user();
        if ($customer == null) {
            return $this->error('Custom account not found.');
        }

        $driver = Administrator::find($r->driver_id);
        if ($driver == null) {
            return $this->error('Driver not found.');
        }

        if (!isset($r->message_body)) {
            return $this->error('Message body not found.');
        }
        if (!isset($r->price)) {
            return $this->error('Price not found.');
        }
        //check for dropoff_lng
        if (!isset($r->dropoff_lng)) {
            return $this->error('Dropoff longitude not found.');
        }
        //check for dropoff_lat
        if (!isset($r->dropoff_lat)) {
            return $this->error('Dropoff latitude not found.');
        }
        //check for dropoff_address
        if (!isset($r->dropoff_address)) {
            return $this->error('Dropoff address not found.');
        }
        //check for pickup_lng
        if (!isset($r->pickup_lng)) {
            return $this->error('Pickup longitude not found.');
        }
        //check for pickup_lat
        if (!isset($r->pickup_lat)) {
            return $this->error('Pickup latitude not found.');
        }
        //check for pickup_address
        if (!isset($r->pickup_address)) {
            return $this->error('Pickup address not found.');
        }


        $old = Negotiation::where([
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'status' => 'Active'
        ])->first();

        $old = Negotiation::where([
            'driver_id' => $driver->id,
            'status' => 'Active'
        ])->first();

        if ($old == null) {
            $negotiation = new Negotiation();
        } else {
            $negotiation = $old;
        }
        $negotiation = new Negotiation();
        $negotiation->customer_id = $customer->id;
        $negotiation->customer_name = $customer->name;
        $negotiation->driver_id = $driver->id;
        $negotiation->driver_name = $driver->name;
        $negotiation->status = 'Active';
        $negotiation->customer_accepted = 'Pending';
        $negotiation->customer_driver = 'Pending';
        $negotiation->pickup_lat = $r->pickup_lat;
        $negotiation->pickup_lng = $r->pickup_lng;
        $negotiation->pickup_address = $r->pickup_address;
        $negotiation->dropoff_lat = $r->dropoff_lat;
        $negotiation->dropoff_lng = $r->dropoff_lng;
        $negotiation->dropoff_address = $r->dropoff_address;
        $negotiation->records = null;
        $negotiation->details = null;
        $negotiation->save();
        if ($negotiation->id < 1) {
            return $this->error('Negotiation not created.');
        }

        // Convert price from dollars to cents
        // Mobile app sends price in dollars (e.g., "1.0", "12.5")
        // Database stores price in cents (e.g., 100, 1250)
        $price_in_dollars = floatval($r->price);
        $price = intval($price_in_dollars * 100); // Convert to cents

        $record = new NegotiationRecord();
        $record->price = $price;
        $record->negotiation_id = $negotiation->id;
        $record->customer_id = $customer->id;
        $record->driver_id = $driver->id;
        $record->last_negotiator_id = $customer->id;
        $record->first_negotiator_id = $customer->id;
        $record->price_accepted = 'No';
        $record->message_type = 'Negotiation';
        $record->message_body = $r->message_body;
        $record->image_url = null;
        $record->audio_url = null;
        $record->is_received = 'No';
        $record->is_seen = 'No';
        $record->latitude = null;
        $record->longitude = null;

        if (!empty($_FILES)) {
            try {
                $file = Utils::upload_images_1($_FILES, true);
                if ($file != null) {
                    if (strlen($file) > 3) {
                        $record->audio_url = $file;
                    }
                }
            } catch (Throwable $e) {
                //return $this->error($e->getMessage());
            }
        }

        $record->save();
        return $this->success($negotiation, 'Success');
    }


    public function negotiations_records_create(Request $r)
    {

        $sender = auth('api')->user();
        if ($sender == null) {
            return $this->error('User not found.');
        }

        if (!isset($r->negotiation_id)) {
            return $this->error('Neg id not found.');
        }

        if (!isset($r->message_type)) {
            return $this->error('Neg type not found.');
        }

        $neg = Negotiation::find($r->negotiation_id);
        if ($neg == null) {
            return $this->error('Neg not found.');
        }

        if ($neg->message_type == 'Negotiation') {
            $lasts = NegotiationRecord::where([
                'negotiation_id' => $neg->id
            ])->orderBy('id', 'desc')
                ->get();
            if ($lasts->count() > 0) {
                if ($lasts[0]->last_negotiator_id == $sender->id) {
                    //return $this->error('Wait for the other party to reply.');
                }
            }
        }

        // Convert price from dollars to cents
        // Mobile app sends price in dollars (e.g., "1.0", "12.5")
        // Database stores price in cents (e.g., 100, 1250)
        $price_in_dollars = floatval($r->price);
        $price = intval($price_in_dollars * 100); // Convert to cents

        $record = new NegotiationRecord();
        $record->price = $price;
        $record->negotiation_id = $neg->id;
        $record->customer_id = $neg->customer_id;
        $record->driver_id = $neg->driver_id;
        $record->last_negotiator_id = $sender->id;
        $record->first_negotiator_id = $neg->customer_id;
        $record->price_accepted = $r->price_accepted;
        $record->message_type = $r->message_type;
        $record->message_body = $r->message_body;
        $record->image_url = null;
        $record->audio_url = null;
        $record->is_received = 'No';
        $record->is_seen = 'No';
        $record->latitude = $r->latitude;
        $record->longitude = $r->longitude;
        $record->save();

        // If this record has an accepted price, update the negotiation's agreed_price
        if ($r->price_accepted == 'Yes' && $price > 0) {
            $neg->agreed_price = $price;
            $neg->payment_status = 'unpaid';
            $neg->save();
        }

        return $this->success($record, 'Success');
    }

    public function negotiations_accept(Request $r)
    {

        $sender = auth('api')->user();
        if ($sender == null) {
            return $this->error('User not found.');
        }

        if (!isset($r->negotiation_id)) {
            return $this->error('Neg id not found.');
        }

        if (!isset($r->message_type)) {
            return $this->error('Neg type not found.');
        }

        $neg = Negotiation::find($r->negotiation_id);
        if ($neg == null) {
            return $this->error('Neg not found.');
        }

        // Helper function to get and set agreed price
        $setAgreedPrice = function($negotiation) {
            // Only set if not already set
            if ($negotiation->agreed_price > 0) {
                return;
            }
            
            // First try to find explicitly accepted price
            $lastRecord = NegotiationRecord::where('negotiation_id', $negotiation->id)
                ->where('price_accepted', 'Yes')
                ->orderBy('id', 'desc')
                ->first();
            
            // If no explicitly accepted price, use the last negotiation record price
            if (!$lastRecord) {
                $lastRecord = NegotiationRecord::where('negotiation_id', $negotiation->id)
                    ->orderBy('id', 'desc')
                    ->first();
            }
            
            if ($lastRecord && $lastRecord->price > 0) {
                $negotiation->agreed_price = $lastRecord->price;
                // Set payment status to unpaid if not already set
                if (empty($negotiation->payment_status) || $negotiation->payment_status == 'unpaid') {
                    $negotiation->payment_status = 'unpaid';
                }
            }
        };

        if ($r->message_type == 'Started') {
            $neg->status = 'Started';
            $neg->customer_accepted = 'Yes';
            $neg->customer_driver = 'Yes';
            
            // Set agreed price when trip is started
            $setAgreedPrice($neg);
            
            $neg->save();
            return $this->success($neg, 'Success');
        }

        if (
            $r->customer_accepted == 'Yes' && $r->customer_driver == 'Yes'
        ) {
            $neg->status = 'Accepted';
            $neg->customer_accepted = 'Yes';
            $neg->customer_driver = 'Yes';
            
            // Set agreed price when negotiation is accepted
            $setAgreedPrice($neg);
            
            $neg->save();
            
            // Get or create Stripe payment link (centralized, prevents duplicates)
            try {
                $paymentLinkInfo = $neg->getOrCreatePaymentLink();
                
                Log::info('✅ Payment link ready for negotiation', [
                    'negotiation_id' => $neg->id,
                    'agreed_price' => $neg->agreed_price,
                    'stripe_url' => $paymentLinkInfo['stripe_url'],
                    'already_existed' => $paymentLinkInfo['already_existed']
                ]);
            } catch (\Exception $e) {
                Log::error('❌ Failed to get/create payment link on accept', [
                    'negotiation_id' => $neg->id,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the accept operation, just log the error
                // Payment link can be retried later
            }
            
            return $this->success($neg, 'Success');
        } else  if (
            $r->customer_accepted == 'No' && $r->customer_driver == 'No'
        ) {
            $neg->status = 'Canceled';
            $neg->customer_accepted = 'No';
            $neg->customer_driver = 'No';
            $neg->save();
            return $this->success($neg, 'Success');
        } else  if (
            $r->status == 'Completed'
        ) {
            $neg->status = 'Completed';
            
            // Ensure agreed price is set even when completing
            $setAgreedPrice($neg);
            
            $neg->save();
            return $this->success($neg, 'Success');
        } else {
            return $this->error('Invalid status.');
        }
    }


    public function negotiations_complete(Request $r)
    {
        $neg = Negotiation::find($r->negotiation_id);
        if ($neg == null) {
            return $this->error('Neg not found.');
        }

        $neg->status = 'Completed';
        $neg->save();
        return $this->success($neg, 'Success');
    }

    public function negotiations_cancel(Request $r)
    {
        $neg = Negotiation::find($r->negotiation_id);
        if ($neg == null) {
            return $this->error('Neg not found.');
        }

        $neg->status = 'Canceled';
        $neg->save();
        return $this->success($neg, 'Success');
    }


    public function negotiations()
    {
        $user = auth('api')->user();
        if ($user == null) {
            return $this->error('User not found.');
        }
        $negotiations = Negotiation::where([
            'customer_id' => $user->id,
        ])->orWhere([
            'driver_id' => $user->id
        ])->get();
        return $this->success($negotiations, 'Success');
    }

    public function negotiations_records(Request $r)
    {
        $user = auth('api')->user();
        if ($user == null) {
            return $this->error('User not found.');
        }
        $recs = [];

        if (isset($r->negotiation_id) && $r->negotiation_id != null) {
            $recs = NegotiationRecord::where([
                'negotiation_id' => $r->negotiation_id,
            ])->get();
            return $this->success($recs, 'Success');
        }

        $recs = NegotiationRecord::where([
            'customer_id' => $user->id,
        ])->orWhere([
            'driver_id' => $user->id
        ])->get();


        return $this->success($recs, 'Success');
    }


    public function chat_heads_create(Request $r)
    {

        $sender = auth('api')->user();
        if ($sender == null) {
            return $this->error('User not found.');
        }

        if ($sender == null) {
            return $this->error('User not found.');
        }

        if ($sender == null) {
            return $this->error('User not found.');
        }
        $receiver = User::find($r->receiver_id);
        if ($receiver == null) {
            return $this->error('Receiver not found.');
        }

        if ($r->product_id != null && trim($r->product_id) != "") {
            $chat_head = ChatHead::where([
                'product_owner_id' => $receiver->id,
                'customer_id' => $sender->id,
                'product_id' => $r->product_id,
            ])->first();

            if ($chat_head != null) {
                return $this->success($chat_head, 'Success');
            }

            $chat_head = ChatHead::where([
                'product_owner_id' => $sender->id,
                'customer_id' => $receiver->id,
                'product_id' => $r->product_id,
            ])->first();

            if ($chat_head != null) {
                return $this->success($chat_head, 'Success');
            }
        } else {
            $chat_head = ChatHead::where([
                'product_owner_id' => $receiver->id,
                'customer_id' => $sender->id,
            ])->first();

            if ($chat_head != null) {
                return $this->success($chat_head, 'Success');
            }

            $chat_head = ChatHead::where([
                'product_owner_id' => $sender->id,
                'customer_id' => $receiver->id,
            ])->first();

            if ($chat_head != null) {
                return $this->success($chat_head, 'Success');
            }
        }


        $trip = Trip::find($r->product_id);
        $chat_head = new ChatHead();
        if ($trip != null) {
            $driver = Administrator::find($trip->driver_id);
            $chat_head->product_id = $trip->id;
            $chat_head->product_owner_id = $receiver->id;
            $chat_head->customer_id = $sender->id;
            $chat_head->product_name = $trip->start_stage_text . " to " . $trip->end_stage_text . " on " . date('d M Y', strtotime($trip->scheduled_start_time));
            $chat_head->product_photo = $receiver->avatar;
            $chat_head->product_owner_name = $receiver->name;
            $chat_head->product_owner_photo = $receiver->avatar;
            $chat_head->customer_name = $sender->name;
            $chat_head->customer_photo = $sender->avatar;
        } else {
            $chat_head->product_id = null;
            $chat_head->product_owner_id = $receiver->id;
            $chat_head->customer_id = $sender->id;
            $chat_head->product_name = $receiver->name;
            $chat_head->product_photo = $receiver->avatar;
            $chat_head->product_owner_name = $receiver->name;
            $chat_head->product_owner_photo = $receiver->avatar;
            $chat_head->customer_name = $sender->name;
            $chat_head->customer_photo = $sender->avatar;
        }
        $chat_head->last_message_body = 'No messages yet.';
        $chat_head->last_message_time = Carbon::now();
        $chat_head->last_message_status = 'sent';
        $chat_head->save();
        $chat_head = ChatHead::find($chat_head->id);
        return $this->success($chat_head, 'Success');
    }


    public function chat_send(Request $r)
    {

        $sender = auth('api')->user();
        if ($sender == null) {
            return $this->error('User not found.');
        }

        if ($sender == null) {
            return $this->error('User not found.');
        }

        if ($sender == null) {
            return $this->error('User not found.');
        }
        $receiver = User::find($r->receiver_id);
        if ($receiver == null) {
            return $this->error('Receiver not found.');
        }

        $chat_head = ChatHead::find($r->chat_head_id);
        if ($chat_head == null) {
            return $this->error('Chat head not found.');
        }

        $chat_message = new ChatMessage();
        $chat_message->chat_head_id = $chat_head->id;
        $chat_message->sender_id = $sender->id;
        $chat_message->receiver_id = $receiver->id;
        $chat_message->sender_name = $sender->name;
        $chat_message->sender_photo = $sender->photo;
        $chat_message->receiver_name = $receiver->name;
        $chat_message->receiver_photo = $receiver->photo;
        $chat_message->body = $r->body;
        $chat_message->type = 'text';
        $chat_message->status = 'sent';
        $chat_message->save();
        $chat_head->last_message_body = $r->body;
        $chat_head->last_message_time = Carbon::now();
        $chat_head->last_message_status = 'sent';
        $chat_head->save();
        return $this->success($chat_message, 'Success');
    }




    public function chat_messages(Request $r)
    {
        $u = auth('api')->user();
        if ($u == null) {
            return $this->error('User not found.');
        }

        if (isset($r->chat_head_id) && $r->chat_head_id != null) {
            $messages = ChatMessage::where([
                'chat_head_id' => $r->chat_head_id
            ])->get();
            return $this->success($messages, 'Success');
        }
        $messages = ChatMessage::where([
            'sender_id' => $u->id
        ])->orWhere([
            'receiver_id' => $u->id
        ])->get();
        return $this->success($messages, 'Success');
    }




    public function chat_heads(Request $r)
    {

        $u = auth('api')->user();

        if ($u == null) {
            return $this->error('User not found.');
        }

        $chat_heads = ChatHead::where([
            'product_owner_id' => $u->id
        ])->orWhere([
            'customer_id' => $u->id
        ])->get();
        $chat_heads->append('customer_unread_messages_count');
        $chat_heads->append('product_owner_unread_messages_count');
        return $this->success($chat_heads, 'Success');
    }

    /**
     * Stripe webhook handler for payment completion
     * Based on lovebirds-api implementation
     * POST /api/webhooks/stripe
     */
    /**
     * Stripe Webhook Handler
     * Enhanced with signature verification and idempotency
     */
    public function stripe_webhook(Request $r)
    {
        $stripe_webhook_secret = env('STRIPE_WEBHOOK_SECRET');
        $payload = $r->getContent();
        $sig_header = $r->header('stripe-signature');

        try {
            // Verify webhook signature
            if ($stripe_webhook_secret && $sig_header) {
                try {
                    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $stripe_webhook_secret);
                    Log::info('✅ Webhook signature verified', ['event_type' => $event['type']]);
                } catch (\Stripe\Exception\SignatureVerificationException $e) {
                    Log::error('❌ Webhook signature verification failed', [
                        'error' => $e->getMessage(),
                        'ip' => $r->ip(),
                    ]);
                    return response()->json(['error' => 'Invalid signature'], 401);
                }
            } else {
                // For development/testing without signature verification
                Log::warning('⚠️ Webhook processing without signature verification');
                $event = json_decode($payload, true);
            }

            // Extract event ID for idempotency
            $event_id = $event['id'] ?? null;
            
            // Check if event already processed (idempotency)
            if ($event_id && \Cache::has("stripe_event_{$event_id}")) {
                Log::info('ℹ️ Skipping duplicate webhook event', ['event_id' => $event_id]);
                return response()->json(['success' => true, 'message' => 'Event already processed']);
            }

            // Mark event as processed (cache for 24 hours)
            if ($event_id) {
                \Cache::put("stripe_event_{$event_id}", true, 86400);
            }

            // Handle the event
            switch ($event['type']) {
                case 'payment_link.payment_completed':
                    $payment_link = $event['data']['object'];
                    $this->handlePaymentLinkCompleted($payment_link, $event_id);
                    break;
                case 'checkout.session.completed':
                    $session = $event['data']['object'];
                    $this->handleCheckoutSessionCompleted($session, $event_id);
                    break;
                default:
                    Log::info('ℹ️ Unhandled Stripe webhook event type', [
                        'type' => $event['type'],
                        'event_id' => $event_id
                    ]);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('❌ Stripe webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle payment link completion
     * Enhanced with better logging and state management
     */
    private function handlePaymentLinkCompleted($payment_link, $event_id = null)
    {
        try {
            $payment_link_id = $payment_link['id'] ?? null;
            
            if (!$payment_link_id) {
                Log::error('❌ Missing payment link ID in webhook');
                return;
            }

            // Find negotiation by payment link ID
            $negotiation = Negotiation::where('stripe_id', $payment_link_id)->first();

            if (!$negotiation) {
                Log::warning('⚠️ Negotiation not found for payment link', [
                    'stripe_id' => $payment_link_id,
                    'event_id' => $event_id,
                ]);
                return;
            }

            // Check if already marked as paid (idempotency at record level)
            if ($negotiation->isPaid()) {
                Log::info('ℹ️ Negotiation already marked as paid', [
                    'negotiation_id' => $negotiation->id,
                    'stripe_id' => $payment_link_id,
                ]);
                return;
            }

            // Use the model's markAsPaid method with full validation
            $success = $negotiation->markAsPaid($payment_link_id);

            if ($success) {
                Log::info('✅ Payment link completion processed successfully', [
                    'negotiation_id' => $negotiation->id,
                    'customer_id' => $negotiation->customer_id,
                    'driver_id' => $negotiation->driver_id,
                    'amount' => $negotiation->agreed_price,
                    'event_id' => $event_id,
                ]);

                // TODO: Send notification to customer and driver
                // TODO: Trigger trip ready event
            } else {
                Log::error('❌ Failed to mark payment as paid', [
                    'negotiation_id' => $negotiation->id,
                    'stripe_id' => $payment_link_id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Error handling payment link completion', [
                'error' => $e->getMessage(),
                'payment_link_id' => $payment_link['id'] ?? 'unknown',
                'event_id' => $event_id,
            ]);
        }
    }

    /**
     * Handle checkout session completion
     * Enhanced with better logging and state management
     * Supports both Negotiation (car hire) and TripBooking (rideshare)
     */
    private function handleCheckoutSessionCompleted($session, $event_id = null)
    {
        try {
            $session_id = $session['id'] ?? null;
            $metadata = $session['metadata'] ?? [];

            // Check if this is a rideshare booking payment
            $booking_id = $metadata['booking_id'] ?? null;
            if ($booking_id) {
                $this->handleRideshareBookingPayment($booking_id, $session_id, $event_id);
                return;
            }

            // Otherwise, treat as negotiation (car hire) payment
            $negotiation_id = $metadata['negotiation_id'] ?? null;

            if (!$negotiation_id) {
                Log::warning('⚠️ No negotiation_id or booking_id in session metadata', [
                    'session_id' => $session_id,
                    'event_id' => $event_id,
                    'metadata' => $metadata,
                ]);
                return;
            }

            $negotiation = Negotiation::find($negotiation_id);

            if (!$negotiation) {
                Log::warning('⚠️ Negotiation not found in checkout session', [
                    'negotiation_id' => $negotiation_id,
                    'session_id' => $session_id,
                ]);
                return;
            }

            // Check if already marked as paid
            if ($negotiation->isPaid()) {
                Log::info('ℹ️ Negotiation already marked as paid (checkout)', [
                    'negotiation_id' => $negotiation->id,
                    'session_id' => $session_id,
                ]);
                return;
            }

            // Use the model's markAsPaid method
            $success = $negotiation->markAsPaid($session_id);

            if ($success) {
                Log::info('✅ Checkout session completion processed', [
                    'negotiation_id' => $negotiation->id,
                    'session_id' => $session_id,
                    'event_id' => $event_id,
                ]);

                // TODO: Send notifications
            } else {
                Log::error('❌ Failed to mark checkout payment as paid', [
                    'negotiation_id' => $negotiation->id,
                    'session_id' => $session_id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Error handling checkout session completion', [
                'error' => $e->getMessage(),
                'negotiation_id' => $session['metadata']['negotiation_id'] ?? 'unknown',
                'event_id' => $event_id,
            ]);
        }
    }

    /**
     * Handle rideshare booking payment completion
     */
    private function handleRideshareBookingPayment($booking_id, $session_id, $event_id = null)
    {
        try {
            Log::info('🚗 Processing rideshare booking payment', [
                'booking_id' => $booking_id,
                'session_id' => $session_id,
                'event_id' => $event_id,
            ]);

            $booking = TripBooking::find($booking_id);

            if (!$booking) {
                Log::warning('⚠️ Booking not found for payment', [
                    'booking_id' => $booking_id,
                    'session_id' => $session_id,
                ]);
                return;
            }

            // Check if already marked as paid (idempotency)
            if ($booking->isPaid()) {
                Log::info('ℹ️ Booking already marked as paid', [
                    'booking_id' => $booking->id,
                    'session_id' => $session_id,
                ]);
                return;
            }

            // Mark as paid and auto-reserve the seat
            $success = $booking->markAsPaid($session_id);

            if ($success) {
                Log::info('✅ Rideshare booking payment processed successfully', [
                    'booking_id' => $booking->id,
                    'trip_id' => $booking->trip_id,
                    'customer_id' => $booking->customer_id,
                    'amount_cents' => $booking->price,
                    'status' => $booking->status,
                    'event_id' => $event_id,
                ]);
            } else {
                Log::error('❌ Failed to mark rideshare booking as paid', [
                    'booking_id' => $booking->id,
                    'session_id' => $session_id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Error handling rideshare booking payment', [
                'error' => $e->getMessage(),
                'booking_id' => $booking_id,
                'event_id' => $event_id,
            ]);
        }
    }

    /**
     * Refresh/create payment link for a negotiation
     * Based on lovebirds-api implementation
     * POST /api/negotiations-refresh-payment
     */
    public function negotiations_refresh_payment(Request $r)
    {
        $u = auth('api')->user();
        if (!$u) {
            return $this->error('Unauthorized');
        }

        $negotiation_id = $r->negotiation_id;
        if (!$negotiation_id) {
            return $this->error('Negotiation ID is required');
        }

        $negotiation = Negotiation::find($negotiation_id);
        if (!$negotiation) {
            return $this->error('Negotiation not found');
        }

        // Verify user is customer or driver
        if ($negotiation->customer_id != $u->id && $negotiation->driver_id != $u->id) {
            return $this->error('You are not authorized to access this negotiation');
        }

        try {
            // Force regenerate if requested
            if ($r->force_regenerate) {
                $negotiation->stripe_id = null;
                $negotiation->stripe_url = null;
                $negotiation->stripe_product_id = null;
                $negotiation->stripe_price_id = null;
                $negotiation->save();
            }

            // Create payment link
            $negotiation->create_payment_link();

            return $this->success([
                'negotiation_id' => $negotiation->id,
                'stripe_url' => $negotiation->stripe_url,
                'stripe_id' => $negotiation->stripe_id,
                'agreed_price' => $negotiation->agreed_price,
                'payment_status' => $negotiation->payment_status,
                'stripe_paid' => $negotiation->stripe_paid,
            ], 'Payment link generated successfully');
        } catch (\Exception $e) {
            \Log::error('Payment link generation failed: ' . $e->getMessage(), [
                'negotiation_id' => $negotiation_id,
                'user_id' => $u->id
            ]);
            return $this->error('Failed to generate payment link: ' . $e->getMessage());
        }
    }

    /**
     * Check Payment Status
     * Checks if a negotiation's payment has been completed
     * If paid via Stripe, updates the negotiation status accordingly
     */
    public function negotiations_check_payment(Request $r)
    {
        $u = auth('api')->user();
        if (!$u) {
            return $this->error('Unauthorized');
        }

        $negotiation_id = $r->negotiation_id;
        if (!$negotiation_id) {
            return $this->error('Negotiation ID is required');
        }

        $negotiation = Negotiation::find($negotiation_id);
        if (!$negotiation) {
            return $this->error('Negotiation not found');
        }

        // Verify user is customer or driver
        if ($negotiation->customer_id != $u->id && $negotiation->driver_id != $u->id) {
            return $this->error('You are not authorized to access this negotiation');
        }

        try {
            // First, sync payment status from Stripe if payment link exists
            if ($negotiation->stripe_id && $negotiation->payment_status !== 'paid') {
                $negotiation->syncPaymentStatusFromStripe();
                // Refresh model to get updated data
                $negotiation->refresh();
            }
            
            // Check if payment is already marked as paid
            $isPaid = $negotiation->isPaid();

            if ($isPaid) {
                // If just discovered as paid, ensure status is updated
                if ($negotiation->payment_status !== 'paid') {
                    $negotiation->payment_status = 'paid';
                    $negotiation->stripe_paid = 'Yes';
                    if (!$negotiation->payment_completed_at) {
                        $negotiation->payment_completed_at = now();
                    }
                    $negotiation->save();

                    \Log::info('💰 Payment status updated via check endpoint', [
                        'negotiation_id' => $negotiation->id,
                        'customer_id' => $negotiation->customer_id,
                        'driver_id' => $negotiation->driver_id,
                        'agreed_price' => $negotiation->agreed_price,
                    ]);
                }

                return $this->success([
                    'payment_status' => 'paid',
                    'stripe_paid' => 'Yes',
                    'is_paid' => true,
                    'payment_completed_at' => $negotiation->payment_completed_at,
                    'agreed_price' => $negotiation->agreed_price,
                    'message' => 'Payment completed successfully! Your trip is ready to start.',
                ], 'Payment is completed');
            } else {
                // If payment is not complete but no payment link exists, try to create one
                if (empty($negotiation->stripe_url) && $negotiation->status === 'Accepted' && $negotiation->agreed_price > 0) {
                    try {
                        $paymentLinkInfo = $negotiation->getOrCreatePaymentLink();
                        
                        \Log::info('💳 Payment link created during status check', [
                            'negotiation_id' => $negotiation->id,
                            'already_existed' => $paymentLinkInfo['already_existed']
                        ]);
                    } catch (\Exception $e) {
                        \Log::warning('Could not create payment link during status check: ' . $e->getMessage());
                    }
                }
                
                return $this->success([
                    'payment_status' => $negotiation->payment_status,
                    'stripe_paid' => $negotiation->stripe_paid ?? 'No',
                    'is_paid' => false,
                    'stripe_url' => $negotiation->stripe_url,
                    'message' => 'Payment is still pending. Please complete the payment.',
                ], 'Payment is pending');
            }
        } catch (\Exception $e) {
            \Log::error('Payment status check failed: ' . $e->getMessage(), [
                'negotiation_id' => $negotiation_id,
                'user_id' => $u->id
            ]);
            return $this->error('Failed to check payment status: ' . $e->getMessage());
        }
    }
}
