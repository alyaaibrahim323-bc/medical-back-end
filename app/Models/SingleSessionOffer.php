<?php
// app/Models/SingleSessionOffer.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SingleSessionOffer extends Model
{
    protected $fillable = [
        'therapist_id','price_cents','currency','duration_min','discount_percent','is_active','price_cents_egp','price_cents_usd',

    ];
    protected $casts = [
        'is_active'=>'boolean',
        'discount_percent'=>'float',
    ];

    public function therapist(){ return $this->belongsTo(Therapist::class); }
}
