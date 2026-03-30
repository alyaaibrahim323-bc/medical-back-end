<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\contracts\Filesystem\Filesystem;

class ChatMessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'chat_id'     => $this->chat_id,
            'sender_id'   => $this->sender_id,
            'sender_role' => $this->sender_role,
            'type'        => $this->type,
            'body'        => $this->body,
            'attachment'  => $this->attachment_path,
            'duration_ms' => $this->duration_ms,
           'created_at' => $this->created_at
                ? $this->created_at->setTimezone('Africa/Cairo')->toIso8601String()
                : null,
            'created_at_ts' => $this->created_at?->timestamp,
            'read_by'     => $this->reads->pluck('user_id'),
            'avatar' => $this->sender->avatar
                        ? asset('storage/' . $this->sender->avatar)
                        : null,
];
    }
}
