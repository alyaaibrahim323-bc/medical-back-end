<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request)
    {
        $data = $this->data ?? [];

        // ✅ 1) حددي اللغة: الهيدر أولاً، بعدين preferred_locale، بعدين en
        $locale = $request->header('Accept-Language')
            ?? $request->user()?->preferred_locale
            ?? 'en';

        $locale = in_array($locale, ['en', 'ar']) ? $locale : 'en';
        app()->setLocale($locale);

        [$title, $body] = $this->resolveText($data, $locale);

        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'title'      => $title,
            'message'    => $body,
            'created_at' => $this->created_at,
            'time_ago'   => $this->created_at?->diffForHumans(),
            'is_read'    => optional($this->pivot)->read_at !== null,
            'status'     =>$this->status,
        ];
    }

    /**
     * Resolve localized title/body based on type + locale.
     */
    protected function resolveText(array $data, string $locale): array
    {
        switch ($this->type) {
            case 'session_upcoming':
                return [
                    __('notifications.session_upcoming.title'),
                    __('notifications.session_upcoming.body', [
                        'doctor' => $data['doctor_name'] ?? $data['doctor'] ?? '',
                        'time'   => isset($data['session_start_at'])
                            ? \Carbon\Carbon::parse($data['session_start_at'])->format('g:i A')
                            : ($data['time'] ?? ''),
                    ]),
                ];

            case 'session_rating':
                return [
                    __('notifications.session_rating.title'),
                    __('notifications.session_rating.body', [
                        'doctor' => $data['doctor_name'] ?? $data['doctor'] ?? '',
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
                // ✅ هنا نستخدم أعمدة ال DB حسب اللغة
                if ($locale === 'ar') {
                    $title = $this->title_ar ?: ($this->title_en ?? '');
                    $body  = $this->body_ar  ?: ($this->body_en ?? '');
                } else {
                    $title = $this->title_en ?: ($this->title_ar ?? '');
                    $body  = $this->body_en  ?: ($this->body_ar ?? '');
                }

                return [$title, $body];
        }
    }
}
