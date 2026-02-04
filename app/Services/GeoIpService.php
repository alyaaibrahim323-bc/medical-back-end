<?php

namespace App\Services;

use Illuminate\Http\Request;
use GeoIp2\Database\Reader;


class GeoIpService
{
    public function clientIp(Request $r): ?string
    {
        $cf = $r->header('CF-Connecting-IP');
        if ($cf && filter_var($cf, FILTER_VALIDATE_IP)) return $cf;

        $xff = $r->header('X-Forwarded-For');
        if ($xff) {
            $first = trim(explode(',', $xff)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
        }

        $ip = $r->ip();
        return ($ip && filter_var($ip, FILTER_VALIDATE_IP)) ? $ip : null;
    }

    public function detectCountryFromIp(?string $ip): ?string
{
    $forced = strtoupper((string) env('PRICING_FORCE_COUNTRY', ''));
    if (strlen($forced) === 2) return $forced;

    if (!$ip || in_array($ip, ['127.0.0.1', '::1'], true)) return null;

    try {
        $path = storage_path('app/geoip/GeoLite2-Country.mmdb');
        $reader = new Reader($path);
        $cc = strtoupper((string) $reader->country($ip)->country->isoCode);
        return strlen($cc) === 2 ? $cc : null;
    } catch (\Throwable $e) {
        return null;
    }
}

    public function regionAndCurrency(?string $countryCode): array
    {
        $cc = strtoupper((string) $countryCode);
        $isEgypt = ($cc === 'EG');

        return [
            'region'   => $isEgypt ? 'EG_LOCAL' : 'INTL',
            'currency' => $isEgypt ? 'EGP' : 'USD',
        ];
    }
}
