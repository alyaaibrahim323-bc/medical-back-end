<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserPackageResource extends JsonResource
{
    public function toArray($request)
    {
        // اسم الباكدج حسب اللغة المطلوبة (?lang=ar/en) مع fallback
        $locale = $request->query('lang', 'en');
        $pkgNameRaw = $this->package->name ?? [];
        if (is_array($pkgNameRaw)) {
            $pkgName = $pkgNameRaw[$locale] ?? ($pkgNameRaw['en'] ?? reset($pkgNameRaw));
        } else {
            $pkgName = (string)$pkgNameRaw;
        }

        $sessionsRemaining = max(0, ($this->sessions_total ?? 0) - ($this->sessions_used ?? 0));

        return [
            'id' => $this->id,

            'client' => [
                'id'    => $this->user_id,
                'name'  => optional($this->user)->name,
                'email' => optional($this->user)->email,
            ],

            'package' => [
                'id'                 => $this->package_id,
                'name'               => $pkgName,
                'sessions_total'     => $this->sessions_total,
                'sessions_used'      => $this->sessions_used,
                'sessions_remaining' => $sessionsRemaining,
                'validity_days'      => $this->package->validity_days ?? null,
                'can_renew'      => (bool) $this->can_renew,

            ],

            'therapist' => $this->therapist ? [
                'id'   => $this->therapist->id,
                'name' => optional($this->therapist->user)->name,
            ] : null,

            'status'       => $this->status,        // active/expired/cancelled
            'purchased_at' => $this->purchased_at,
            'expires_at'   => $this->expires_at,

            // (اختياري) الاستهلاك بالتفصيل لو محتاجين
            'redemptions'  => $this->whenLoaded('redemptions', function(){
                return $this->redemptions->map(function($r){
                    return [
                        'session_id'    => $r->therapy_session_id,
                        'redeemed_at'   => $r->redeemed_at,
                        'refunded_at'   => $r->refunded_at,
                    ];
                });
            }),
        ];
    }
}
