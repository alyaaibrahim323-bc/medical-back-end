<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChatResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'type'            => $this->type,
            'session_id'      => $this->therapy_session_id,
            'status'          => $this->status,
            'last_message_at' => $this->last_message_at?->toIso8601String(),

            'client' => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ],

            'therapist' => $this->therapist ? [
                'id'             => $this->therapist->id,
                'name'           => $this->therapist->user->name ?? $this->therapist->name ?? null,
                'avatar'         => $this->therapist->avatar,
                'is_chat_online' => (bool) $this->therapist->is_chat_online,
            ] : null,

            // 👇 هنا التعديل المهم
            'session' => $this->session ? [
                'scheduled_at' => $this->session->scheduled_at
                    ? $this->session->scheduled_at->toIso8601String()
                    : null,
                'status'       => $this->session->status,
            ] : null,

            // لو حابة ترجعي الرسائل مع الشات
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
