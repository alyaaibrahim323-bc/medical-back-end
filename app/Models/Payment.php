<?php

// app/Models/Payment.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
      'user_id','therapist_id','therapy_session_id','user_package_id',
      'purpose','amount_cents','currency',
      'provider','provider_order_id','provider_transaction_id',
      'status','paid_at','failed_at','refunded_at','payload','reference'
    ];

    protected $casts = [
      'payload' => 'array',
      'paid_at' => 'datetime',
      'failed_at' => 'datetime',
      'refunded_at' => 'datetime',
    ];

    public function user()            { return $this->belongsTo(User::class); }
    public function therapist()       { return $this->belongsTo(Therapist::class); }
    public function therapySession()  { return $this->belongsTo(TherapySession::class); }
    public function userPackage()     { return $this->belongsTo(UserPackage::class); }
}
