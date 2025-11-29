<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\UserPackage;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PackageCheckoutController extends Controller
{
    public function checkout(Request $r, $id)
{
    $user = $r->user();

    $package = Package::where('is_active', true)->findOrFail($id);

    $therapistId = $package->created_by_therapist_id;

    $basePrice = (int) $package->price_cents;
    $discount  = (float) ($package->discount_percent ?? 0);
    $payable   = (int) round($basePrice * (100 - $discount) / 100);

    $serviceFee = (int) config('fees.package_service_cents', 0);
    $total      = $payable + $serviceFee;

    // ✅ نخلى الترانزاكشن ترجع القيم بدل الـ & references
    [$userPackage, $payment] = DB::transaction(function () use (
        $user,
        $package,
        $therapistId,
        $payable,
        $serviceFee,
        $total,
        $basePrice,
        $discount
    ) {
        // 1) UserPackage
        $userPackage = UserPackage::create([
            'user_id'        => $user->id,
            'therapist_id'   => $therapistId,
            'package_id'     => $package->id,
            'sessions_total' => $package->sessions_count,
            'sessions_used'  => 0,
            'status'         => 'active',
            'expires_at'     => $package->validity_days
                ? now()->addDays($package->validity_days)
                : null,
            'is_paid'        => true,
        ]);

        // 2) Payment
        $payment = Payment::create([
            'user_id'           => $user->id,
            'therapist_id'      => $therapistId,
            'therapy_session_id'=> null,
            'user_package_id'   => $userPackage->id,
            'purpose'           => 'package',
            'amount_cents'      => $total,
            'currency'          => $package->currency ?? 'EGP',
            'provider'          => 'paymob',
            'status'            => 'pending',
            'reference'         => 'PKG-' . Str::uuid(),
            'payload'           => [
                'package_id'          => $package->id,
                'base_price_cents'    => $basePrice,
                'discount_percent'    => $discount,
                'service_fee_cents'   => $serviceFee,
                'sessions_count'      => $package->sessions_count,
                'session_duration_min'=> $package->session_duration_min,
                'validity_days'       => $package->validity_days,
            ],
        ]);

        return [$userPackage, $payment];
    });

    // من هنا Intelephense عارف إنهم objects مش null ✅
    $userPackage->load(['package', 'therapist.user']);

    $packageData = [
        'id'                   => $userPackage->package->id,
        'name'                 => $userPackage->package->name_localized,
        'description'          => $userPackage->package->description_localized,
        'sessions_count'       => $userPackage->package->sessions_count,
        'session_duration_min' => $userPackage->package->session_duration_min,
        'price_cents'          => $userPackage->package->price_cents,
        'currency'             => $userPackage->package->currency,
        'discount_percent'     => $userPackage->package->discount_percent,
    ];

    $therapistData = $userPackage->therapist ? [
        'id'        => $userPackage->therapist->id,
        'name'      => $userPackage->therapist->user?->name,
        'email'     => $userPackage->therapist->user?->email,
        'avatar'    => $userPackage->therapist->user?->avatar,
        'specialty' => $userPackage->therapist->specialtyText,
        'bio'       => $userPackage->therapist->bioText,
    ] : null;

    return response()->json([
        'data' => [
            'user_package' => [
                'id'             => $userPackage->id,
                'sessions_total' => $userPackage->sessions_total,
                'sessions_used'  => $userPackage->sessions_used,
                'expires_at'     => $userPackage->expires_at,
                'package'        => $packageData,
                'therapist'      => $therapistData,
            ],
            'payment' => [
                'id'                => $payment->id,
                'amount_cents'      => $payment->amount_cents,
                'currency'          => $payment->currency,
                'session_fee_cents' => $payable,
                'service_fee_cents' => $serviceFee,
                'reference'         => $payment->reference,
            ],
        ]
    ], 201);
}

}
