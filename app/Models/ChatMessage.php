<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class ChatMessage extends Model
{
    protected $fillable = [
        'chat_id',
        'sender_id',
        'sender_role',
        'type',
        'body',
        'attachment_path',
        'duration_ms',
        'replied_to_id',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function repliedTo(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'replied_to_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ChatRead::class, 'message_id');
    }
}
