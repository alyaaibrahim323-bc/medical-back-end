<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChatResource extends JsonResource
{
    public function toArray($request): array
    {
        // نحاول نجيب آخر مسدچ:
        // - لو في relation اسمها lastMessage ومحمّلة → نستخدمها
        // - غير كده نستخدم أول message من messages مرتّبة تنازليًا
        $lastMessage = null;

        if ($this->relationLoaded('lastMessage') && $this->lastMessage) {
            $lastMessage = $this->lastMessage;
        } elseif ($this->relationLoaded('messages') && $this->messages->count() > 0) {
            $lastMessage = $this->messages->sortByDesc('created_at')->first();
        }

        return [
            'id'              => $this->id,
            'type'            => $this->type,
            'session_id'      => $this->therapy_session_id,
            'status'          => $this->status,
            'last_message_at' => $this->last_message_at?->toIso8601String(),

            // 👇 بيانات الكلاينت + avatar
            'client' => [
                'id'     => $this->user->id,
                'name'   => $this->user->name,
                'email'  => $this->user->email,
                'avatar' => $this->user->avatar ?? null,
            ],

            // 👇 بيانات الثيرابست (لو الشات متاساين)
            'therapist' => $this->therapist ? [
                'id'             => $this->therapist->id,
                'name'           => $this->therapist->user->name
                                    ?? $this->therapist->name
                                    ?? null,
                // لو ال avatar متخزّن فى user مش فى therapist:
                'avatar'         => $this->therapist->user->avatar
                                    ?? $this->therapist->avatar
                                    ?? null,
                'is_chat_online' => (bool) $this->therapist->is_chat_online,
            ] : null,

            'session' => $this->session ? [
                'scheduled_at' => $this->session->scheduled_at
                    ? $this->session->scheduled_at->toIso8601String()
                    : null,
                'status'       => $this->session->status,
            ] : null,

            // 👇 آخر رسالة (مبسّطة) – للليست
            'last_message' => $lastMessage
                ? [
                    'id'         => $lastMessage->id,
                    'body'       => $lastMessage->body,
                    'type'       => $lastMessage->type,
                    'sender_id'  => $lastMessage->sender_id,
                    'sender_role'=> $lastMessage->sender_role,
                    'created_at' => $lastMessage->created_at?->toIso8601String(),
                ]
                : null,

            // 👇 لو في سكرين التفاصيل وبتحمّلي الرسائل
            'messages' => ChatMessageResource::collection(
                $this->whenLoaded('messages', function () {
                    return $this->messages
                        ->sortBy('created_at')
                        ->values();
                })
            ),
        ];
    }
}
