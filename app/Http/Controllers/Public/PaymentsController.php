<?php
// app/Http/Controllers/Public/PaymentsController.php
namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\TherapySession;
use App\Models\UserPackage;
use App\Services\PaymobService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PaymentsController extends Controller
{
    // 4.1 بدء دفع (جلسة أو باكيدج)
    public function create(Request $r, PaymobService $paymob)
    {
        $data = $r->validate([
          'purpose'   => ['required','in:single_session,package'],
          'id'        => ['required','integer'], // session_id أو user_package_id حسب الغرض
          'billing'   => ['required','array'],   // name,email,phone,country,city,address
        ]);

        $user = $r->user();

        // Resolve target & amount
        if ($data['purpose'] === 'single_session') {
            $s = TherapySession::with('therapist')->where('user_id',$user->id)->findOrFail($data['id']);
            if ($s->status !== TherapySession::STATUS_PENDING_PAYMENT) {
                return response()->json(['message'=>'Session not payable'], 422);
            }
            $amount = (int) $s->therapist->price_cents; // سعر الدكتور الجاري
            $therapistId = $s->therapist_id;
            $sessionId = $s->id; $userPackageId = null;
        } else {
            $up = UserPackage::with(['package','therapist'])->where('user_id',$user->id)->findOrFail($data['id']);
            if ($up->is_paid ?? false) return response()->json(['message'=>'Package already paid'], 409);
            $amount = (int) $up->package->price_cents;
            $therapistId = $up->therapist_id;
            $sessionId = null; $userPackageId = $up->id;
        }

        $reference = strtoupper(Str::random(10));
        $payment = DB::transaction(function() use ($user,$therapistId,$sessionId,$userPackageId,$data,$amount,$reference) {
            return Payment::create([
                'user_id'           => $user->id,
                'therapist_id'      => $therapistId,
                'therapy_session_id'=> $sessionId,
                'user_package_id'   => $userPackageId,
                'purpose'           => $data['purpose'],
                'amount_cents'      => $amount,
                'currency'          => 'EGP',
                'provider'          => 'paymob',
                'status'            => 'pending',
                'reference'         => $reference,
            ]);
        });

        // Paymob
        $token = $paymob->auth();
        $order  = $paymob->createOrder($token, $amount, $payment->reference, []);
        $orderId = $order['id'] ?? null;

        $billing = array_merge([
            'first_name' => $user->name ?? 'User',
            'last_name'  => 'Customer',
            'email'      => $user->email,
            'phone_number'=> $user->phone ?? 'NA',
            'apartment'=>'NA','floor'=>'NA','street'=>'NA','building'=>'NA',
            'shipping_method'=>'NA','postal_code'=>'00000','city'=>'Cairo','country'=>'EG','state'=>'Cairo'
        ], $data['billing']);

        $pk = $paymob->paymentKey(
            $token,
            $amount,
            $orderId,
            $billing,
            (int) config('services.paymob.integration_id_card')
        );

        // خزّن order_id
        $payment->update(['provider_order_id' => (string)($orderId ?? '')]);

        // رجّع key + iframe URL (لو بتستخدمي Iframe)
        return response()->json([
          'message' => 'Payment initiated',
          'data' => [
            'payment_id'   => $payment->id,
            'reference'    => $payment->reference,
            'amount_cents' => $payment->amount_cents,
            'currency'     => $payment->currency,
            'paymob'       => [
              'order_id'    => $orderId,
              'payment_key' => $pk['token'] ?? null,
              // مثال Iframe:
              'iframe_url'  => $pk['token'] ? ('https://accept.paymob.com/api/acceptance/iframes/'.env('PAYMOB_IFRAME_ID').'?payment_token='.$pk['token']) : null,
            ],
          ]
        ], 201);
    }

    // 4.2 Webhook: Paymob يحوّلنا هنا بعد الدفع
    public function webhook(Request $r, PaymobService $paymob)
    {
        $hmac = $r->query('hmac');
        $data = $r->all();

        if (!$paymob->verifyHmac($data, (string)$hmac)) {
            return response()->json(['message'=>'Invalid HMAC'], 403);
        }

        // حددي المرجع (merchant_order_id) اللي حطيناه = reference
        $merchantOrderId = data_get($data, 'order.merchant_order_id');
        $txnId = (string) data_get($data, 'id');
        $success = (bool) data_get($data, 'success');

        $payment = Payment::where('reference',$merchantOrderId)->first();
        if (!$payment) return response()->json(['message'=>'Payment not found'], 404);

        $update = [
          'provider_transaction_id' => $txnId,
          'payload' => $data,
        ];
        if ($success) {
            $update['status'] = 'paid';
            $update['paid_at'] = now();
        } else {
            $update['status'] = 'failed';
            $update['failed_at'] = now();
        }

        DB::transaction(function() use ($payment,$update) {
            $payment->update($update);

            // لو جلسة مفردة → حدّث حالتها من pending_payment → confirmed
            if ($payment->purpose === 'single_session' && $payment->therapy_session_id) {
                $s = \App\Models\TherapySession::find($payment->therapy_session_id);
                if ($s && $payment->status === 'paid') {
                    $s->update(['status' => \App\Models\TherapySession::STATUS_CONFIRMED]);
                }
            }

            // لو باكيدج → علّميه مدفوعًا (لو عندك عمود is_paid)
            if ($payment->purpose === 'package' && $payment->user_package_id && $payment->status === 'paid') {
                \App\Models\UserPackage::where('id',$payment->user_package_id)->update(['is_paid'=>true]);
            }
        });

        return response()->json(['message'=>'Webhook processed']);
    }

    // 4.3 صفحات نجاح/فشل (مناسبة للديب لينك في الموبايل)
    public function success() { return response()->json(['message'=>'Payment success']); }
    public function failed()  { return response()->json(['message'=>'Payment failed']);  }
}
