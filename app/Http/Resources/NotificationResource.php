<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NotificationResource extends JsonResource
{
    public function toArray($request)
    {
        $data = $this->data ?? [];

        // ✅ locale
        $locale = $request->header('Accept-Language')
            ?? $request->user()?->preferred_locale
            ?? 'en';

        $locale = in_array($locale, ['en', 'ar']) ? $locale : 'en';
        app()->setLocale($locale);

        // ✅ resolve text
        [$title, $body] = $this->resolveText(is_array($data) ? $data : (array)$data, $locale);

        // (اختياري) debug
        Log::info('NOTIF_RESOURCE_RESOLVED', [
            'notif_id' => $this->id,
            'type' => $this->type,
            'title' => $title,
            'message' => $body,
            'data' => $data,
        ]);

        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'title'      => $title,
            'message'    => $body,
            'created_at' => $this->created_at,
            'time_ago'   => $this->created_at?->diffForHumans(),
            'is_read'    => optional($this->pivot)->read_at !== null,
            'status'     => $this->status,
        ];
    }

    protected function resolveText(array $data, string $locale): array
    {
        switch ($this->type) {

            case 'session_upcoming':
                return [
                    __('notifications.session_upcoming.title'),
                    __('notifications.session_upcoming.body', [
                        'doctor' => $data['doctor_name'] ?? $data['doctor'] ?? '',
                        'time'   => isset($data['session_start_at'])
                            ? Carbon::parse($data['session_start_at'])->format('g:i A')
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

            // ✅ انتِ بتبعتي النوع ده
            case 'session_link_ready':
                return [
                    __('notifications.session_link_ready.title'),
                    // لو بعتّي message جوه data استخدمه
                    $data['message']
                        ?? __('notifications.session_link_ready.body'),
                ];

            case 'payment_success_session':
                return [
                    __('notifications.payment_success_session.title'),
                    __('notifications.payment_success_session.body', [
                        'amount' => isset($data['amount_cents']) ? ($data['amount_cents'] / 100) : '',
                        'currency' => $data['currency'] ?? 'EGP',
                    ]),
                ];

            case 'payment_success_package':
                return [
                    __('notifications.payment_success_package.title'),
                    __('notifications.payment_success_package.body', [
                        'amount' => isset($data['amount_cents']) ? ($data['amount_cents'] / 100) : '',
                        'currency' => $data['currency'] ?? 'EGP',
                    ]),
                ];

            default:
                // ✅ fallback: خد title/message من data لو موجودين
                $title = $data['title'] ?? '';
                $body  = $data['message'] ?? $data['body'] ?? '';

                // لو فاضيين، جرّب أعمدة DB لو موجودة فعلًا عندك
                if ($title === '' && $body === '') {
                    if ($locale === 'ar') {
                        $title = $this->title_ar ?: ($this->title_en ?? '');
                        $body  = $this->body_ar  ?: ($this->body_en ?? '');
                    } else {
                        $title = $this->title_en ?: ($this->title_ar ?? '');
                        $body  = $this->body_en  ?: ($this->body_ar ?? '');
                    }
                }

                return [$title, $body];
        }
    }
}
