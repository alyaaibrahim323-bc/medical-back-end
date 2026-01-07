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
        $lastPayment = $this->whenLoaded('lastPaidPayment') ? $this->lastPaidPayment : null;

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
                 'price_cents'        => $this->package->price_cents ?? null,           // السعر الأساسي
                'discount_percent'   => $this->package->discount_percent ?? null,      // لو موجود في package
                'payable_cents'      => $lastPayment?->amount_cents ?? null,           // اللي اتدفع فعلاً
                'currency'           => $lastPayment?->currency
                    ?? $this->package->currency
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
            'method'            => $lastPayment?->method
                ?? data_get($lastPayment?->payload, 'kashier_webhook.data.method'),
            'provider'          => $lastPayment?->provider,
            'status'            => $lastPayment?->status,
            'paid_at'           => $lastPayment?->paid_at,
            'transaction_id'    => $lastPayment?->provider_transaction_id,
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
