<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TherapistSchedule extends Model
{
    protected $fillable = ['therapist_id','weekday','start_time','end_time','slot_minutes','is_active'];

    protected $casts = ['is_active'=>'boolean'];
    protected $appends = ['weekday_name'];

public function getWeekdayNameAttribute(): ?string
{
    $map = [
        0 => 'sunday',
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
    ];
    return $map[$this->weekday] ?? null;
}


    public function therapist() { return $this->belongsTo(Therapist::class); }
}
