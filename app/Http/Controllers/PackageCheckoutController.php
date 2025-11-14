<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Payment;
use App\Services\PaymobService;
use Illuminate\Http\Request;

class PackageCheckoutController extends Controller
{
    public function checkout($id, Request $r, PaymobService $paymob)
    {
        $p = Package::where('is_active',true)->findOrFail($id);

        $therapistId = null;
        if ($p->applicability === 'therapist') {
            $therapistId = $r->validate(['therapist_id'=>['required','exists:therapists,id']])['therapist_id'];
        }

        $payment = Payment::create([
            'therapy_session_id'=>null,
            'provider'=>'paymob',
            'amount_cents'=>$p->price_cents,
            'currency'=>$p->currency,
            'status'=>'initiated',
            'payload'=>[
                'is_package'=>true,
                'package_id'=>$p->id,
                'buyer_user_id'=>auth()->id(),
                'therapist_id'=>$therapistId
            ],
        ]);

        $init = $paymob->initPayment($payment->amount_cents, auth()->user()->email, auth()->user()->name, 'pkg_'.$payment->id.'_'.time());
        $payment->update(['order_id'=>$init['order_id']]);

        return response()->json(['data'=>['payment'=>['order_id'=>$init['order_id'],'payment_url'=>$init['payment_url']]]], 201);
    }
}
