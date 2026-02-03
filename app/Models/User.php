<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Contracts\Auth\MustVerifyEmail;


class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name','email','password','phone','role','status','preferred_locale','device_token','avatar','email_verified_at','country_code','pricing_region','geo_detected_at',

    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'geo_detected_at'   => 'datetime',
    ];
public function sendPasswordResetNotification($token)
{
    $this->notify(new \App\Notifications\ResetPasswordNotification($token));
}

public function therapist()
{
    return $this->hasOne(\App\Models\Therapist::class, 'user_id');
}

public function notifications()
{
    return $this->belongsToMany(Notification::class, 'notification_deliveries')
        ->withPivot(['delivered_at','read_at'])
        ->withTimestamps();
}

public function notificationSettings()
{
    return $this->hasOne(NotificationSetting::class);
}


public function therapySessions()
{
    return $this->hasMany(TherapySession::class, 'user_id');
}




}
