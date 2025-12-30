<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPackage extends Model
{
    protected $fillable = [
      'user_id','package_id','therapist_id','sessions_total','sessions_used',
      'purchased_at','expires_at','status','payment_id'
    ];
    protected $casts = ['purchased_at'=>'datetime','expires_at'=>'datetime'];

    protected $appends = [
        'can_renew',
    ];

    public function user(){ return $this->belongsTo(User::class); }
    public function package(){ return $this->belongsTo(Package::class); }
    public function therapist(){ return $this->belongsTo(Therapist::class); }
    public function payment(){ return $this->belongsTo(Payment::class); }
    public function redemptions(){ return $this->hasMany(PackageRedemption::class); }
    public function payments()
{
    return $this->hasMany(Payment::class);
}


    public function getCanRenewAttribute(): bool
    {
        $noSessionsLeft = $this->sessions_used >= $this->sessions_total;
        $isExpired      = $this->expires_at && $this->expires_at->isPast();

        return $noSessionsLeft || $isExpired ;
    }
}
