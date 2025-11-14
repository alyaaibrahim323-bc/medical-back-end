<?php
// app/Http/Resources/SingleSessionOfferResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SingleSessionOfferResource extends JsonResource
{
    public function toArray($request)
    {
        $final = (int) round($this->price_cents * (100 - $this->discount_percent) / 100);

        return [
            'enabled'              => (bool)$this->is_active,
            'title'                => 'Single Session',
            'price_cents'          => $this->price_cents,
            'currency'             => $this->currency,
            'discount_percent'     => (float)$this->discount_percent,
            'final_price_cents'    => $final,
            'session_duration_min' => $this->duration_min,
            'display' => [
                'badge'          => 'Single Session',
                'price_label'    => ($this->price_cents/100).' '.$this->currency,
                'discount_label' => $this->discount_percent > 0 ? (intval($this->discount_percent).'% Off') : null,
                'duration_label' => $this->duration_min.' m Session Duration',
                'sessions_label' => '1 Session',
                'cta'            => 'Start Booking'
            ]
        ];
    }
}
