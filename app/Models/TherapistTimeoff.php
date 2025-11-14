<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TherapistTimeoff extends Model
{
    protected $fillable = ['therapist_id','off_date','reason'];

    protected $casts = ['off_date'=>'date'];

    public function therapist() { return $this->belongsTo(Therapist::class); }
}
