<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentIndexesAndFailureReasonToNegotiationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('negotiations', function (Blueprint $table) {
            // Add payment_failure_reason column
            $table->text('payment_failure_reason')->nullable()->after('stripe_paid');
            
            // Add indexes for faster payment queries
            $table->index('payment_status', 'idx_payment_status');
            $table->index('stripe_paid', 'idx_stripe_paid');
            $table->index('stripe_id', 'idx_stripe_id');
            $table->index(['customer_id', 'payment_status'], 'idx_customer_payment');
            $table->index(['driver_id', 'payment_status'], 'idx_driver_payment');
            $table->index(['status', 'payment_status'], 'idx_status_payment');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('negotiations', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('idx_payment_status');
            $table->dropIndex('idx_stripe_paid');
            $table->dropIndex('idx_stripe_id');
            $table->dropIndex('idx_customer_payment');
            $table->dropIndex('idx_driver_payment');
            $table->dropIndex('idx_status_payment');
            
            // Drop column
            $table->dropColumn('payment_failure_reason');
        });
    }
}
