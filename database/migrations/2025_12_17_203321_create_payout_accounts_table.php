<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayoutAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();
            
            // Driver Association
            $table->unsignedBigInteger('user_id')->unique();
            
            // Account Type & Status
            $table->enum('account_type', ['express', 'standard', 'custom'])->default('express');
            $table->enum('status', ['pending', 'active', 'restricted', 'disabled', 'rejected'])->default('pending');
            
            // Stripe Connect Information
            $table->string('stripe_account_id')->unique()->nullable();
            $table->string('stripe_person_id')->nullable(); // For identity verification
            
            // Onboarding & Verification Status
            $table->boolean('onboarding_completed')->default(false);
            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('details_submitted')->default(false);
            
            // Banking Information (Last 4 digits only - Stripe stores full details)
            $table->string('bank_account_last4')->nullable();
            $table->string('bank_account_type')->nullable(); // checking, savings
            $table->string('bank_account_country', 2)->default('CA');
            $table->string('bank_name')->nullable();
            
            // Debit Card Information (for instant payouts)
            $table->string('card_last4')->nullable();
            $table->string('card_brand')->nullable(); // visa, mastercard
            $table->string('card_country', 2)->nullable();
            
            // Verification & Requirements
            $table->enum('verification_status', ['unverified', 'pending', 'verified', 'failed'])->default('unverified');
            $table->text('verification_fields_needed')->nullable(); // JSON array of fields
            $table->text('requirements_currently_due')->nullable(); // JSON array
            $table->text('requirements_eventually_due')->nullable(); // JSON array
            $table->text('requirements_past_due')->nullable(); // JSON array
            $table->timestamp('requirements_due_by')->nullable();
            
            // Payout Settings
            $table->enum('default_payout_method', ['standard', 'instant'])->default('standard');
            $table->string('default_currency', 3)->default('CAD');
            $table->decimal('minimum_payout_amount', 10, 2)->default(10.00);
            
            // Business Information (if applicable)
            $table->string('business_name')->nullable();
            $table->string('business_type')->nullable(); // individual, company
            $table->text('business_profile')->nullable(); // JSON
            
            // Identity Information (Encrypted/Tokenized - minimal storage)
            $table->string('email')->nullable(); // Driver's payout email
            $table->string('phone')->nullable(); // Driver's phone for 2FA
            $table->string('country', 2)->default('CA');
            
            // Stripe Dashboard Access
            $table->string('stripe_dashboard_url')->nullable(); // Express dashboard link
            $table->timestamp('last_stripe_sync')->nullable();
            
            // Metadata & Additional Info
            $table->text('metadata')->nullable(); // JSON for additional data
            $table->text('admin_notes')->nullable(); // Internal notes
            
            // Audit Trail
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->string('disabled_reason')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('user_id');
            $table->index('stripe_account_id');
            $table->index('status');
            $table->index('verification_status');
            $table->index(['payouts_enabled', 'status']); // For finding eligible accounts
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payout_accounts');
    }
}
