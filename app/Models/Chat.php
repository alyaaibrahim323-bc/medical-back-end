<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany,HasOne};

class Chat extends Model
{
    protected $fillable = [
        'type',
        'therapy_session_id',
        'user_id',
        'therapist_id',
        'status',
        'last_message_at',
        'last_client_message_at',
        'last_therapist_message_at',
        'assigned_by',
        'assigned_at',
    ];

    protected $casts = [
        'last_message_at'           => 'datetime',
        'last_client_message_at'    => 'datetime',
        'last_therapist_message_at' => 'datetime',
        'assigned_at'               => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TherapySession::class, 'therapy_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(Therapist::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

      public function lastMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }
}
