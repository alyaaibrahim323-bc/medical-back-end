<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminChatResource extends JsonResource
{
    public function toArray($request): array
    {
        $lastMsg = $this->messages()->latest()->first();

        return [
            'id'                  => $this->id,
            'client_name'         => $this->user->name,
            'client_email'        => $this->user->email,
            'avatar'              =>$this->user->avatar,
            'assigned_therapist'  => $this->therapist?->user?->name,
            'avatar_therapist'  => $this->therapist?->user?->avatar,
            'status'              => $this->status,
            'last_message'        => $lastMsg?->body,
            'last_from'           => $lastMsg?->sender_role,
            'last_message_at'     => $this->last_message_at?->toDateTimeString(),

            // 👇 هنا التعديل المهم
            'session_date'        => $this->session
                                        ? ($this->session->scheduled_at
                                            ? $this->session->scheduled_at->toDateString()
                                            : null)
                                        : null,

            'session_status'      => $this->session?->status,
        ];
    }
}
