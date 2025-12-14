<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('payments'))
            Schema::create('payments', function (Blueprint $table) {
                $table->id();

                // Relationships
                $table->unsignedBigInteger('negotiation_id');
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('driver_id');

                // Stripe Integration
                $table->string('stripe_payment_intent_id')->unique()->nullable();
                $table->string('stripe_customer_id')->nullable();
                $table->string('stripe_payment_method')->nullable();

                // Payment Amounts (in CAD)
                $table->decimal('amount', 10, 2); // Total amount
                $table->decimal('service_fee', 10, 2)->default(0); // Platform fee
                $table->decimal('driver_amount', 10, 2); // Amount driver receives

                // Payment Status
                $table->enum('status', [
                    'pending',
                    'processing',
                    'requires_action',
                    'succeeded',
                    'failed',
                    'canceled',
                    'refunded'
                ])->default('pending');

                $table->enum('payment_type', [
                    'ride_payment',
                    'wallet_topup',
                    'refund'
                ])->default('ride_payment');

                // Additional Information
                $table->string('currency', 3)->default('cad');
                $table->text('description')->nullable();
                $table->text('failure_reason')->nullable();
                $table->json('metadata')->nullable(); // Store additional Stripe data

                // Timestamps
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index('negotiation_id');
                $table->index('customer_id');
                $table->index('driver_id');
                $table->index('status');
                $table->index('stripe_payment_intent_id');

                // Foreign Keys
                $table->foreign('negotiation_id')
                    ->references('id')
                    ->on('negotiations')
                    ->onDelete('cascade');

                $table->foreign('customer_id')
                    ->references('id')
                    ->on('admin_users')
                    ->onDelete('cascade');

                $table->foreign('driver_id')
                    ->references('id')
                    ->on('admin_users')
                    ->onDelete('cascade');
            });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
