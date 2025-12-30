<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TherapySession extends Model
{
    protected $table = 'therapy_sessions';

    protected $fillable = [
        'user_id','therapist_id','scheduled_at','duration_min','status',
        'zoom_meeting_id','zoom_join_url','zoom_start_url',
        'user_package_id','billing_type','billing_status','notes'
    ];

    protected $casts = ['scheduled_at'=>'datetime'];

    const STATUS_PENDING   = 'pending_payment';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_NO_SHOW   = 'no show';

    public function user(){ return $this->belongsTo(User::class); }
    public function therapist(){ return $this->belongsTo(Therapist::class); }
    public function payment(){ return $this->hasOne(Payment::class, 'therapy_session_id'); }
    public function userPackage(){ return $this->belongsTo(UserPackage::class); }

    public function scopeForDoctor($q, int $therapistId) {return $q->where('therapist_id', $therapistId);}
    public function scopeUpcoming($q) { return $q->where('scheduled_at','>=', now()); }
    public function scopePast($q) { return $q->where('scheduled_at','<', now()); }
    public function chat()
    {
        return $this->hasOne(Chat::class);
    }


}
