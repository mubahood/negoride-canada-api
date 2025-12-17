<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayoutRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            
            // User & Account
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('payout_account_id');
            
            // Amount & Currency
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2); // amount - fee_amount
            
            // Status
            $table->enum('status', [
                'pending',
                'processing', 
                'completed',
                'failed',
                'cancelled'
            ])->default('pending');
            
            // Payout Method
            $table->enum('payout_method', ['standard', 'instant'])->default('standard');
            
            // Stripe References
            $table->string('stripe_transfer_id')->nullable();
            $table->string('stripe_payout_id')->nullable();
            
            // Description & Notes
            $table->text('description')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('failure_reason')->nullable();
            
            // Timestamps
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes (No foreign keys - DB doesn't support cascading)
            $table->index('user_id');
            $table->index('payout_account_id');
            $table->index('status');
            $table->index('requested_at');
            $table->index('stripe_transfer_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payout_requests');
    }
}
