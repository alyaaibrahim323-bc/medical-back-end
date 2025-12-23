<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

use App\Models\Chat;
use App\Policies\ChatPolicy;

class AuthServiceProvider extends ServiceProvider
{
    
    protected $policies = [
        Chat::class => ChatPolicy::class,
    ];

    
    public function boot(): void
    {
        //
    }
}
