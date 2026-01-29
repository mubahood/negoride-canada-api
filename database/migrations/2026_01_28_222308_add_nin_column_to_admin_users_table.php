<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNinColumnToAdminUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('admin_users', 'nin')) {
            Schema::table('admin_users', function (Blueprint $table) {
                $table->string('nin')->nullable()->comment('National ID / SIN Number');
            });
        }
        if (!Schema::hasColumn('admin_users', 'driving_license_issue_authority')) {
            Schema::table('admin_users', function (Blueprint $table) {
                $table->string('driving_license_issue_authority')->nullable()->comment('License issuing authority');
            });
        }
        if (!Schema::hasColumn('admin_users', 'driving_license_issue_date')) {
            Schema::table('admin_users', function (Blueprint $table) {
                $table->date('driving_license_issue_date')->nullable()->comment('License issue date');
            });
        }
        if (!Schema::hasColumn('admin_users', 'driving_license_validity')) {
            Schema::table('admin_users', function (Blueprint $table) {
                $table->date('driving_license_validity')->nullable()->comment('License expiry date');
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
            $table->dropColumn(['nin', 'driving_license_issue_authority', 'driving_license_issue_date', 'driving_license_validity']);
        });
    }
}
