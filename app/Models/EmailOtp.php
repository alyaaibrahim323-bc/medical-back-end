<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailOtp extends Model
{
    protected $fillable = ['user_id','code_hash','attempts','expires_at','last_sent_at','consumed_at'];
    protected $casts = ['expires_at'=>'datetime','last_sent_at'=>'datetime','consumed_at'=>'datetime'];
}
