<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Therapist extends Model
{
    protected $fillable = [
        'user_id','specialty','bio','price_cents','currency',
        'rating_avg','rating_count','is_active','is_chat_online','last_online_at','avatar'
    ];

    protected $casts = [
        'specialty' => 'array', // {"en": "...", "ar": "..."}
        'bio'       => 'array',
        'is_active' => 'boolean',
        'rating_avg'=> 'float',
        'is_chat_online' => 'boolean',
        'last_online_at' => 'datetime',
    ];

public function user()
{
    return $this->belongsTo(\App\Models\User::class, 'user_id');
}
    public function schedules() { return $this->hasMany(TherapistSchedule::class); }
    public function timeoffs()  { return $this->hasMany(TherapistTimeoff::class); }

    // Accessors تُرجّع النص حسب اللغة الحالية (Accept-Language)
    protected function specialtyText(): Attribute {
        return Attribute::get(function () {
            $loc = app()->getLocale() ?: 'en';
            $arr = $this->specialty ?? [];
            return $arr[$loc] ?? $arr['en'] ?? null;
        });
    }
    protected function bioText(): Attribute {
        return Attribute::get(function () {
            $loc = app()->getLocale() ?: 'en';
            $arr = $this->bio ?? [];
            return $arr[$loc] ?? $arr['en'] ?? null;
        });

    }

    public function sessions()
{
    return $this->hasMany(TherapySession::class);
}

}

