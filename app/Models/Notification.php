<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'type',
        'title_en','title_ar',
        'body_en','body_ar',
        'data',
        'status',
        'created_by',
        'scheduled_at','sent_at',
    ];

    protected $casts = [
        'data'         => 'array',
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
    ];

    public function deliveries()
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'notification_deliveries')
            ->withPivot(['delivered_at','read_at'])
            ->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
