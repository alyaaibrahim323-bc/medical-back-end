<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocaleFromRequest
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->header('Accept-Language') ?? $request->user()?->preferred_locale ?? 'en';
        app()->setLocale(in_array($locale, ['en','ar']) ? $locale : 'en');
        return $next($request);
    }
}
