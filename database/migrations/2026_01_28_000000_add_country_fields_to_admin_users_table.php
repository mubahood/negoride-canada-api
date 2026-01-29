<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCountryFieldsToAdminUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add country_name
        if (!Schema::hasColumn('admin_users', 'country_name')) {
            Schema::table('admin_users', function (Blueprint $table) {
                $table->string('country_name', 50)->nullable()->default('Canada')->after('phone_number');
            });
        }
        
        // Add country_code
        if (!Schema::hasColumn('admin_users', 'country_code')) {
            Schema::table('admin_users', function (Blueprint $table) {
                $table->string('country_code', 5)->nullable()->default('+1')->after('country_name');
            });
        }
        
        // Add country_short_name
        if (!Schema::hasColumn('admin_users', 'country_short_name')) {
            Schema::table('admin_users', function (Blueprint $table) {
                $table->string('country_short_name', 3)->nullable()->default('CA')->after('country_code');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('admin_users', function (Blueprint $table) {
            if (Schema::hasColumn('admin_users', 'country_name')) {
                $table->dropColumn('country_name');
            }
            if (Schema::hasColumn('admin_users', 'country_code')) {
                $table->dropColumn('country_code');
            }
            if (Schema::hasColumn('admin_users', 'country_short_name')) {
                $table->dropColumn('country_short_name');
            }
        });
    }
}
