<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Payment;

class UserPackageResource extends JsonResource
{
    public function toArray($request)
    {
        $locale     = $request->query('lang', 'en');
        $pkgNameRaw = $this->package->name ?? [];

        if (is_array($pkgNameRaw)) {
            $pkgName = $pkgNameRaw[$locale]
                ?? ($pkgNameRaw['en'] ?? reset($pkgNameRaw));
        } else {
            $pkgName = (string) $pkgNameRaw;
        }

        $sessionsRemaining = max(
            0,
            ($this->sessions_total ?? 0) - ($this->sessions_used ?? 0)
        );

        return [
            'id' => $this->id,

            'client' => [
                'id'     => $this->user_id,
                'name'   => optional($this->user)->name,
                'email'  => optional($this->user)->email,
                // 🆕 avatar بتاع اليوزر (عدّلي اسم العمود لو مختلف)
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

                // 🆕 السعر (من جدول الباكدج أو من user_package حسب ما عندك)
                'price_cents' => $this->package->price_cents ?? $this->price_cents ?? null,
                'price'       => isset($this->package->price_cents)
                    ? $this->package->price_cents / 100
                    : (isset($this->price_cents) ? $this->price_cents / 100 : null),
                'currency'    => $this->package->currency
                    ?? $this->currency
                    ?? 'EGP',
            ],

            'therapist' => $this->therapist ? [
                'id'     => $this->therapist->id,
                'name'   => optional($this->therapist->user)->name,
                'email'  => optional($this->therapist->user)->email,
                // 🆕 avatar بتاع الثيرابست (من user بتاعه)
                'avatar' => optional($this->therapist->user)->avatar,
            ] : null,

            // 🆕 بلوك بسيط للدفع: طريقة الدفع + ممكن تزودي فيه حاجات تانية
            'payment' => [
                'method'         => $this->payment_method,   // مثال: card / wallet / cash
                'last_paid_at' => $this->paid_at ?? null,          // لو عندك عمود زى كدا
            ],

            'status'       => $this->status,        // active/expired/cancelled
            'purchased_at' => $this->purchased_at,
            'expires_at'   => $this->expires_at,

            // (اختياري) الاستهلاك بالتفصيل لو محتاجين
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
