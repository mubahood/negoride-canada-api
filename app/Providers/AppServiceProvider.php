<?php

namespace App\Providers;

use App\Observers\AdministratorObserver;
use Encore\Admin\Auth\Database\Administrator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Register observer to auto-approve drivers when services are approved
        Administrator::observe(AdministratorObserver::class);
    }
}
