<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\GeoIpService;

class EnsureUserGeo
{
    public function handle(Request $request, Closure $next)
    {
        Log::info('GEO_DEBUG', [
        'laravel_ip' => $request->ip(),
        'cf_ip'      => $request->header('CF-Connecting-IP'),
        'xff'        => $request->header('X-Forwarded-For'),
        'remote'     => $request->server('REMOTE_ADDR'),
        'user_agent' => $request->userAgent(),
    ]);
        $user = $request->user();
        if (!$user) return $next($request);

        if (
            !empty($user->pricing_region) &&
            !empty($user->geo_detected_at) &&
            optional($user->geo_detected_at)->gt(now()->subDays(7))
        ) {
            return $next($request);
        }

        try {
            $geo = app(GeoIpService::class);

            $ip = $geo->clientIp($request);
            $country = $geo->detectCountryFromIp($ip);

            if (!$country) {
                return $next($request);
            }

            $map = $geo->regionAndCurrency($country);

            $user->forceFill([
                'country_code'    => $country,
                'pricing_region'  => $map['region'],
                'geo_detected_at' => now(),
            ])->save();

        } catch (\Throwable $e) {
            Log::warning('ENSURE_USER_GEO_FAILED', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return $next($request);
    }
}
