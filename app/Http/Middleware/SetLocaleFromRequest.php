<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocaleFromRequest
{
    public function handle(Request $request, Closure $next)
    {
        $locale = null;

        // 1) أعلى أولوية: ?lang=ar أو ?lang=en
        $locale = $request->query('lang');

        // 2) لو مش موجودة: هيدر مخصص من الفرونت X-Locale: ar / en
        if (! $locale) {
            $locale = $request->header('X-Locale');
        }

        // 3) لو لسه مفيش: ناخد أول لغة من Accept-Language (مثلاً ar من ar-EG,ar;q=0.9,en;q=0.8)
        if (! $locale) {
            $accept = $request->header('Accept-Language'); // string أو null
            if ($accept) {
                // ناخد أول 2 حروف بس (ar, en, fr...)
                $locale = substr($accept, 0, 2);
            }
        }

        // 4) لو مفيش حاجة من فوق: نجرب preferred_locale بتاع اليوزر لو لوجين
        if (! $locale && $request->user()) {
            $locale = $request->user()->preferred_locale ?? null;
        }

        // 5) لو لسه فاضي: استخدم default من config/app.php
        if (! $locale) {
            $locale = config('app.locale', 'en');
        }

        // 6) نسمح بس باللغات اللي إحنا داعمينها
        $supported = ['en', 'ar'];
        if (! in_array($locale, $supported, true)) {
            $locale = 'en';
        }

        // 7) ثبت اللغة على مستوى التطبيق
        app()->setLocale($locale);

        // (اختياري) خلى اللى عايز من الكنترولرز يقدر يجيبها من request
        $request->attributes->set('resolved_locale', $locale);

        return $next($request);
    }
}
