<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentFieldsToNegotiationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('negotiations', function (Blueprint $table) {
            // Payment-related fields
            $table->decimal('agreed_price', 10, 2)->nullable()->after('is_active');
            $table->enum('payment_status', [
                'unpaid',
                'pending',
                'paid',
                'failed',
                'refunded'
            ])->default('unpaid')->after('agreed_price');
            $table->unsignedBigInteger('payment_id')->nullable()->after('payment_status');
            $table->timestamp('payment_completed_at')->nullable()->after('payment_id');
            
            // Foreign key
            $table->foreign('payment_id')
                  ->references('id')
                  ->on('payments')
                  ->onDelete('set null');
            
            // Index
            $table->index('payment_status');
            $table->index('payment_id');
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
            $table->dropForeign(['payment_id']);
            $table->dropColumn([
                'agreed_price',
                'payment_status',
                'payment_id',
                'payment_completed_at'
            ]);
        });
    }
}
