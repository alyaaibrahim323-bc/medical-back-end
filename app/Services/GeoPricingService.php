<?php

namespace App\Services;

use Illuminate\Http\Request;

class GeoPricingService
{
    public function detectCountryCode(Request $r): ?string
    {
        // ✅ للتست السريع
        $forced = env('PRICING_FORCE_COUNTRY');
        if ($forced) return strtoupper($forced);

        $ip = $this->getClientIp($r);
        if (!$ip) return null;

        try {
            $loc = geoip($ip);              // من torann/geoip
            $cc  = $loc->iso_code ?? null;  // EG / US
            return $cc ? strtoupper($cc) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function pricingRegionFromCountry(?string $cc): string
    {
        return strtoupper((string)$cc) === 'EG' ? 'EG_LOCAL' : 'INTL';
    }

    public function shouldRefresh($geoDetectedAt): bool
    {
        if (!$geoDetectedAt) return true;
        return now()->diffInDays($geoDetectedAt) >= 7; // كل أسبوع
    }

    private function getClientIp(Request $r): ?string
    {
        // لو Cloudflare قدامك
        $cf = $r->header('CF-Connecting-IP');
        if ($cf && filter_var($cf, FILTER_VALIDATE_IP)) return $cf;

        $ip = $r->ip();

        // لوكال ممكن يطلع 127.0.0.1
        if (!$ip || in_array($ip, ['127.0.0.1', '::1'], true)) return null;

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }
}
