<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'user_id','therapist_id','therapy_session_id','user_package_id',
        'purpose','amount_cents','currency',
        'provider','provider_order_id','provider_transaction_id','provider_payment_id',
        'status','paid_at','failed_at','refunded_at',
        'payload','reference',
    ];

    protected $casts = [
        'payload'     => 'array',
        'paid_at'     => 'datetime',
        'failed_at'   => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public const PURPOSE_SINGLE_SESSION = 'single_session';
    public const PURPOSE_PACKAGE        = 'package';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_PAID     = 'paid';
    public const STATUS_FAILED   = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function therapist()
    {
        return $this->belongsTo(Therapist::class);
    }

    public function therapySession()
    {
        return $this->belongsTo(TherapySession::class);
    }

    public function userPackage()
    {
        return $this->belongsTo(UserPackage::class);
    }
    public function getPaymentMethodAttribute()
{
    $provider = strtolower($this->provider ?? '');

    return match ($provider) {
        'paymob'       => 'credit_card',
        'card'         => 'credit_card',
        'visa'         => 'credit_card',
        'mastercard'   => 'credit_card',

        'wallet'       => 'wallet',
        'vodafone'     => 'wallet',
        'etisalat'     => 'wallet',

        'fawry'        => 'kiosk',
        'kiosk'        => 'kiosk',

        'cash'         => 'cash',

        default        => 'other',
    };
}

}
