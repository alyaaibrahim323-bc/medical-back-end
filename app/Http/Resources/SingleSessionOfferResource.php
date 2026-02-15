<?php
// app/Http/Resources/SingleSessionOfferResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SingleSessionOfferResource extends JsonResource
{
   public function toArray($request)
{
    $discountPercent = (float) ($this->discount_percent ?? 0);

    $region  = strtoupper((string) optional($request->user())->pricing_region);
    $isLocal = ($region === 'EG_LOCAL');

    // ✅ السعر الحقيقي المعروض
    $basePriceCents = $isLocal
        ? (int) ($this->price_cents_egp ?? 0)
        : (int) ($this->price_cents_usd ?? 0);

    // ✅ fallback لو السعر المتخصص = 0 (احتياطي)
    if ($basePriceCents <= 0) {
        $basePriceCents = (int) ($this->price_cents ?? 0);
    }

    $currency = $isLocal ? 'EGP' : 'USD';

    $final = (int) round($basePriceCents * (100 - $discountPercent) / 100);

    return [
        'enabled'              => (bool)$this->is_active,
        'title'                => 'Single Session',

        'price_cents'          => $basePriceCents,
        'currency'             => $currency,

        'discount_percent'     => $discountPercent,
        'final_price_cents'    => $final,
        'session_duration_min' => (int)$this->duration_min,

        // لو محتاجاهم للعرض في داشبورد الدكتور أو debug
        'price_cents_egp'      => (int)$this->price_cents_egp,
        'price_cents_usd'      => (int)$this->price_cents_usd,

        'display' => [
            'badge'          => 'Single Session',
            'price_label'    => number_format($basePriceCents / 100, 2) . ' ' . $currency,
            'discount_label' => $discountPercent > 0 ? (intval($discountPercent) . '% Off') : null,
            'duration_label' => ((int)$this->duration_min) . ' m Session Duration',
            'sessions_label' => '1 Session',
            'cta'            => 'Start Booking',
        ],
    ];
}

}
