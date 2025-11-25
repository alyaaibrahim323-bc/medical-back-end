<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $fillable = [
        'user_id',
        'general','session','rating','security','system',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
