<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TherapistChatAvailability extends Model
{
    protected $fillable = [
        'therapist_id',
        'day_of_week',
        'from_time',
        'to_time',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_active'   => 'boolean',
    ];

    public function therapist()
    {
        return $this->belongsTo(Therapist::class);
    }
}
