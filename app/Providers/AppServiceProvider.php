<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\User;
use App\Observers\UserObserver;
use App\Models\TherapySession;
use App\Observers\TherapySessionObserver;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        User::observe(UserObserver::class);
        TherapySession::observe(TherapySessionObserver::class);

    }
}
