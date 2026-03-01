<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDescriptionAndLuggageFieldsToScheduledBookings extends Migration
{
    public function up()
    {
        Schema::table('scheduled_bookings', function (Blueprint $table) {
            $table->text('pickup_description')->nullable()->after('pickup_address');
            $table->text('destination_description')->nullable()->after('destination_address');
            $table->integer('luggage_weight_lbs')->default(0)->after('luggage');
            $table->text('luggage_description')->nullable()->after('luggage_weight_lbs');
        });
    }

    public function down()
    {
        Schema::table('scheduled_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_description',
                'destination_description',
                'luggage_weight_lbs',
                'luggage_description',
            ]);
        });
    }
}
