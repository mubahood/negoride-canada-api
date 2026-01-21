<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStripePaymentFieldsToTripBookingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('trip_bookings', function (Blueprint $table) {
            // Stripe payment fields (following Negotiation model pattern)
            $table->string('stripe_id')->nullable()->after('driver_notes');
            $table->string('stripe_url', 500)->nullable()->after('stripe_id');
            $table->string('stripe_product_id')->nullable()->after('stripe_url');
            $table->string('stripe_price_id')->nullable()->after('stripe_product_id');
            $table->string('stripe_paid')->default('No')->after('stripe_price_id');
            $table->timestamp('payment_completed_at')->nullable()->after('stripe_paid');
            $table->string('payment_failure_reason')->nullable()->after('payment_completed_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('trip_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_id',
                'stripe_url',
                'stripe_product_id',
                'stripe_price_id',
                'stripe_paid',
                'payment_completed_at',
                'payment_failure_reason',
            ]);
        });
    }
}
