<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request)
    {
        $data   = $this->data ?? [];
        $locale = $request->user()->preferred_locale ?? 'en';
        app()->setLocale($locale);

        [$title, $body] = $this->resolveText($data);

        return [
            'id'        => $this->id,
            'type'      => $this->type,
            'title'     => $title,
            'message'   => $body,
            'created_at'=> $this->created_at,
            'time_ago'  => $this->created_at?->diffForHumans(),
            'is_read'   => optional($this->pivot)->read_at !== null,
        ];
    }

    protected function resolveText(array $data): array
    {
        switch ($this->type) {
            case 'session_upcoming':
                return [
                    __('notifications.session_upcoming.title'),
                    __('notifications.session_upcoming.body', [
                        'doctor' => $data['doctor_name'] ?? '',
                        'time'   => isset($data['session_start_at'])
                            ? \Carbon\Carbon::parse($data['session_start_at'])->format('g:i A')
                            : '',
                    ]),
                ];

            case 'session_rating':
                return [
                    __('notifications.session_rating.title'),
                    __('notifications.session_rating.body', [
                        'doctor' => $data['doctor_name'] ?? '',
                    ]),
                ];

            case 'system_update':
                return [
                    __('notifications.system_update.title'),
                    __('notifications.system_update.body', [
                        'message' => $data['message'] ?? '',
                    ]),
                ];

            default:
                // fallback: استخدم العنوان/النص من الـ DB
                return [
                    $this->title_en ?? '',
                    $this->body_en ?? '',
                ];
        }
    }
}
