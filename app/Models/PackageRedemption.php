<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageRedemption extends Model
{
    protected $fillable = ['user_package_id','therapy_session_id','redeemed_at','refunded_at'];
    protected $casts = ['redeemed_at'=>'datetime','refunded_at'=>'datetime'];

    public function userPackage(){ return $this->belongsTo(UserPackage::class); }
    public function therapySession(){ return $this->belongsTo(TherapySession::class); }
}
