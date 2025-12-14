<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserWalletsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //drop if exists
        if (Schema::hasTable('user_wallets')) {
            Schema::drop('user_wallets');
        }

        Schema::create('user_wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->decimal('wallet_balance', 10, 2)->default(0);
            $table->decimal('total_earnings', 10, 2)->default(0);
            $table->string('stripe_customer_id')->unique()->nullable();
            $table->string('stripe_account_id')->unique()->nullable();
            $table->timestamps();
            
         
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_wallets');
    }
}
