<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //drop table if exists
        if (Schema::hasTable('transactions')) {
            Schema::drop('transactions');
        }
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // User Reference
            $table->unsignedBigInteger('user_id'); // Either customer or driver
            $table->enum('user_type', ['customer', 'driver']); // Who this transaction belongs to

            // Payment Reference (nullable for wallet operations)
            $table->unsignedBigInteger('payment_id')->nullable();

            // Transaction Details
            $table->enum('type', [
                'credit',  // Money added to wallet
                'debit'    // Money deducted from wallet
            ]);

            $table->enum('category', [
                'ride_payment',     // Customer pays for ride
                'ride_earning',     // Driver receives payment
                'service_fee',      // Platform service fee deduction
                'refund',           // Refund to customer
                'wallet_topup',     // Manual wallet topup
                'withdrawal',       // Driver withdrawal
                'bonus',            // Promotional bonus
                'penalty'           // Administrative penalty
            ]);

            $table->decimal('amount', 10, 2); // Transaction amount
            $table->decimal('balance_before', 10, 2)->default(0); // Balance before transaction
            $table->decimal('balance_after', 10, 2)->default(0); // Balance after transaction

            // Transaction Context
            $table->string('reference')->unique(); // Unique transaction reference (e.g., TXN-123456)
            $table->text('description');
            $table->enum('status', [
                'pending',
                'completed',
                'failed',
                'reversed'
            ])->default('completed');

            // Additional Information
            $table->unsignedBigInteger('related_user_id')->nullable(); // The other party (if ride payment: driver for customer, customer for driver)
            $table->unsignedBigInteger('negotiation_id')->nullable(); // Associated negotiation
            $table->text('metadata')->nullable(); // Store additional data

            $table->timestamps();
            $table->softDeletes();

            // // Indexesson
            // $table->index('user_id');
            // $table->index('payment_id');
            // $table->index('type');
            // $table->index('category');
            // $table->index('status');
            // $table->index('reference');
            // $table->index('negotiation_id');
            // $table->index(['user_id', 'created_at']); // For transaction history queries

            // // Foreign Keys
            // $table->foreign('user_id')
            //     ->references('id');

            // $table->foreign('payment_id')
            //     ->references('id');

            // $table->foreign('related_user_id')
            //     ->references('id');

            // $table->foreign('negotiation_id')
            //     ->references('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
