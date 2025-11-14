<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Package;
use App\Models\UserPackage;
use App\Models\TherapySession;
use App\Services\PaymobService;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function paymob(Request $r, PaymobService $paymob)
    {
        $data = $r->all();
        if (!$paymob->verifyHmac($data)) {
            return response()->json(['message'=>'Invalid HMAC'], 400);
        }

        $success = filter_var($data['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $orderId = (string)($data['order'] ?? $data['order_id'] ?? '');

        $p = Payment::where('order_id',$orderId)->first();
        if (!$p) return response()->json(['message'=>'Payment not found'], 404);

        $p->update([
            'transaction_id'=>(string)($data['id'] ?? $p->transaction_id),
            'status'=>$success ? 'paid' : 'failed',
            'paid_at'=>$success ? now() : null,
            'payload'=>$data + (array)$p->payload,
        ]);

        if (!$success) return response()->json(['message'=>'ok']);

        // شراء باكدج
        if (($p->payload['is_package'] ?? false) === true) {
            $pkg = Package::findOrFail($p->payload['package_id']);
            $expiresAt = $pkg->validity_days ? now()->addDays($pkg->validity_days) : null;

            UserPackage::create([
                'user_id'        => $p->payload['buyer_user_id'],
                'package_id'     => $pkg->id,
                'therapist_id'   => $p->payload['therapist_id'] ?? null,
                'sessions_total' => $pkg->sessions_count,
                'sessions_used'  => 0,
                'purchased_at'   => now(),
                'expires_at'     => $expiresAt,
                'status'         => 'active',
                'payment_id'     => $p->id,
            ]);
            return response()->json(['message'=>'ok']);
        }

        // دفع جلسة مفردة
        if ($p->therapy_session_id) {
            $s = TherapySession::find($p->therapy_session_id);
            if ($s && $s->status === TherapySession::STATUS_PENDING) {
                $s->update(['status'=>TherapySession::STATUS_CONFIRMED,'billing_status'=>'paid']);
                dispatch(new \App\Jobs\CreateZoomAndNotifyJob($s->id));
            }
        }

        return response()->json(['message'=>'ok']);
    }
}
