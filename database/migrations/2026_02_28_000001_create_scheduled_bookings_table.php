<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScheduledBookingsTable extends Migration
{
    public function up()
    {
        Schema::create('scheduled_bookings', function (Blueprint $table) {
            $table->id();

            // Parties
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable(); // admin user who assigned

            // Service info
            $table->string('service_type', 100);        // e.g. NegoRide, Airport Pickup
            $table->string('automobile_type', 100)->nullable(); // e.g. Car, Van

            // Pickup
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->string('pickup_place_name', 300)->nullable();
            $table->string('pickup_address', 500);

            // Destination
            $table->decimal('destination_lat', 10, 7);
            $table->decimal('destination_lng', 10, 7);
            $table->string('destination_place_name', 300)->nullable();
            $table->string('destination_address', 500);

            // Trip details
            $table->tinyInteger('passengers')->default(1);
            $table->tinyInteger('luggage')->default(0);
            $table->text('message')->nullable();

            // Schedule
            $table->dateTime('scheduled_at');

            // Pricing — all in CENTS
            $table->bigInteger('customer_proposed_price')->default(0); // customer's initial offer
            $table->bigInteger('driver_proposed_price')->nullable();   // driver counter-offer
            $table->bigInteger('agreed_price')->nullable();            // final agreed price

            // Booking status
            $table->string('status', 50)->default('pending');
            // pending | driver_assigned | price_negotiating | price_accepted
            // payment_pending | payment_completed | confirmed | in_progress | completed | cancelled

            // Payment status
            $table->string('payment_status', 50)->default('unpaid');
            // unpaid | pending | paid | failed

            // Stripe fields
            $table->string('stripe_id')->nullable();
            $table->text('stripe_url')->nullable();
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->boolean('stripe_paid')->default(false);
            $table->timestamp('payment_completed_at')->nullable();

            // Timeline
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Notes
            $table->text('cancellation_reason')->nullable();
            $table->text('driver_notes')->nullable();
            $table->text('admin_notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('customer_id');
            $table->index('driver_id');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index('payment_status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('scheduled_bookings');
    }
}
