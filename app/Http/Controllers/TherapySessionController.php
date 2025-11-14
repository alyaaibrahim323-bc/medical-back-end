<?php

namespace App\Http\Controllers;

use App\Models\TherapySession;
use App\Models\Payment;
use App\Models\Therapist;
use App\Models\UserPackage;
use App\Models\PackageRedemption;
use App\Services\PaymobService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TherapySessionController extends Controller
{
    public function index(Request $r)
    {
        $scope = $r->query('scope'); // upcoming|past|null
        $q = TherapySession::with(['therapist.user','payment'])
            ->where('user_id', Auth::id())
            ->when($scope==='upcoming', fn($x)=>$x->where('scheduled_at','>=',now()))
            ->when($scope==='past', fn($x)=>$x->where('scheduled_at','<',now()))
            ->orderBy('scheduled_at','desc');

        return response()->json(['data'=>$q->paginate(20)]);
    }

    public function store(Request $r, PaymobService $paymob)
    {
        $data = $r->validate([
            'therapist_id'=>['required','exists:therapists,id'],
            'scheduled_at'=>['required','date','after:now'],
            'duration_min'=>['nullable','integer','min:30','max:180'],
            'use_package' =>['nullable','boolean']
        ]);

        $t = Therapist::findOrFail($data['therapist_id']);
        if (!$t->is_active) return response()->json(['message'=>'Therapist not available'], 422);

        $userPackage = null;
        if ($r->boolean('use_package')) {
            $userPackage = UserPackage::query()
                ->where('user_id', Auth::id())
                ->where('status','active')
                ->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>=', now()))
                ->whereColumn('sessions_used','<','sessions_total')
                ->where(fn($q)=>$q->whereNull('therapist_id')->orWhere('therapist_id',$t->id))
                ->orderBy('id','asc')
                ->first();
        }

        $session = TherapySession::create([
            'user_id'=>Auth::id(),
            'therapist_id'=>$t->id,
            'scheduled_at'=>Carbon::parse($data['scheduled_at']),
            'duration_min'=>$data['duration_min'] ?? 60,
            'status'=>$userPackage ? TherapySession::STATUS_CONFIRMED : TherapySession::STATUS_PENDING,
            'billing_type'=>$userPackage ? 'package' : 'single',
            'billing_status'=>$userPackage ? 'covered' : 'pending',
            'user_package_id'=>$userPackage?->id,
        ]);

        if ($userPackage) {
            PackageRedemption::create([
                'user_package_id'=>$userPackage->id,
                'therapy_session_id'=>$session->id,
                'redeemed_at'=>now(),
            ]);
            $userPackage->increment('sessions_used');

            dispatch(new \App\Jobs\CreateZoomAndNotifyJob($session->id));

            return response()->json([
                'data'=>[
                    'session'=>$session->fresh(['therapist.user']),
                    'payment'=>null,
                    'billing'=>['type'=>'package','status'=>'covered']
                ]
            ], 201);
        }

        // دفع جلسة مفردة
        $payment = Payment::create([
            'therapy_session_id'=>$session->id,
            'provider'=>'paymob',
            'amount_cents'=>$t->price_cents,
            'currency'=>$t->currency ?? 'EGP',
            'status'=>'initiated',
        ]);

        $init = $paymob->initPayment(
            $payment->amount_cents,
            Auth::user()->email,
            Auth::user()->name,
            'ts_'.$session->id.'_'.time()
        );

        $payment->update(['order_id'=>$init['order_id']]);

        return response()->json([
            'data'=>[
                'session'=>$session,
                'payment'=>['order_id'=>$init['order_id'],'payment_url'=>$init['payment_url']],
                'billing'=>['type'=>'single','status'=>'pending']
            ]
        ], 201);
    }

    public function show($id)
    {
        $s = TherapySession::with(['therapist.user','payment'])
            ->where('user_id', Auth::id())->findOrFail($id);
        return response()->json(['data'=>$s]);
    }

    public function cancel($id)
    {
        $s = TherapySession::where('user_id', Auth::id())->findOrFail($id);
        if (!in_array($s->status,[TherapySession::STATUS_PENDING, TherapySession::STATUS_CONFIRMED])) {
            return response()->json(['message'=>'Cannot cancel this session'], 422);
        }
        // سياسة الكريدت التفصيلية هنكمّلها في المرحلة 4
        $s->update(['status'=>TherapySession::STATUS_CANCELLED]);
        return response()->json(['message'=>'Cancelled']);
    }
}
