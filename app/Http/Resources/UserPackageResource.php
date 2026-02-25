<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserPackageResource extends JsonResource
{
    public function toArray($request)
    {
        $locale     = $request->query('lang', 'en');
        $pkgNameRaw = $this->package->name ?? [];

        if (is_array($pkgNameRaw)) {
            $pkgName = $pkgNameRaw[$locale] ?? ($pkgNameRaw['en'] ?? reset($pkgNameRaw));
        } else {
            $pkgName = (string) $pkgNameRaw;
        }

        $sessionsRemaining = max(0, (int)($this->sessions_total ?? 0) - (int)($this->sessions_used ?? 0));

        $lastPayment = $this->whenLoaded('lastPaidPayment') ? $this->lastPaidPayment : null;

        $region  = strtoupper((string) (optional($this->user)->pricing_region ?? ''));
        $isLocal = ($region === 'EG_LOCAL');

        $baseFee = $isLocal
            ? (int) ($this->package->price_cents_egp ?? 0)
            : (int) ($this->package->price_cents_usd ?? 0);

        if ($baseFee <= 0) {
            $baseFee = (int) ($this->package->price_cents ?? 0);
        }

        $fallbackCurrency = $isLocal ? 'EGP' : 'USD';

        $discountPercent = (float) ($this->package->discount_percent ?? 0);
        $discountPercent = max(0, min(100, $discountPercent));

        $discountCents = (int) round($baseFee * $discountPercent / 100);

        $computedPayable = max(0, $baseFee - $discountCents);

        return [
            'id' => $this->id,

            'client' => [
                'id'     => $this->user_id,
                'name'   => optional($this->user)->name,
                'email'  => optional($this->user)->email,
                'avatar' => optional($this->user)->avatar,
            ],

            'package' => [
                'id'                 => $this->package_id,
                'name'               => $pkgName,
                'sessions_total'     => $this->sessions_total,
                'sessions_used'      => $this->sessions_used,
                'sessions_remaining' => $sessionsRemaining,
                'validity_days'      => $this->package->validity_days ?? null,
                'can_renew'          => (bool) $this->can_renew,

                'base_fee_cents'        => $baseFee,
                'discount_percent'      => $discountPercent,
                'discount_amount_cents' => $discountCents,

                'payable_cents'         => $lastPayment?->amount_cents ?? $computedPayable,

                'currency'              => $lastPayment?->currency ?? $fallbackCurrency,
            ],

            'therapist' => $this->therapist ? [
                'id'     => $this->therapist->id,
                'name'   => optional($this->therapist->user)->name,
                'email'  => optional($this->therapist->user)->email,
                'avatar' => optional($this->therapist->user)->avatar,
            ] : null,

            'payment' => [
                'method'         => data_get($lastPayment?->payload, 'kashier_webhook.data.method'),
                'provider'       => $lastPayment?->provider,
                'status'         => $lastPayment?->status,
                'paid_at'        => $lastPayment?->paid_at,
                'transaction_id' => $lastPayment?->provider_transaction_id,
            ],

            'status'       => $this->status,
            'purchased_at' => $this->purchased_at,
            'expires_at'   => $this->expires_at,

            'redemptions'  => $this->whenLoaded('redemptions', function () {
                return $this->redemptions->map(function ($r) {
                    return [
                        'session_id'  => $r->therapy_session_id,
                        'redeemed_at' => $r->redeemed_at,
                        'refunded_at' => $r->refunded_at,
                    ];
                });
            }),
        ];
    }
}
