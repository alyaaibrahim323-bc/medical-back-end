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

        // لو الباكيدج بتاعة دكتور معيّن
        $therapistId = $package->created_by_therapist_id; // ممكن يبقى null لو global

        // السعر الأساسى + الخصم
        $basePrice = (int) $package->price_cents;
        $discount  = (float) ($package->discount_percent ?? 0);
        $payable   = (int) round($basePrice * (100 - $discount) / 100);

        // service fee (اختيارى من config)
        $serviceFee = (int) config('fees.package_service_cents', 0);
        $total      = $payable + $serviceFee;

        $userPackage = null;
        $payment     = null;

        DB::transaction(function () use (
            $user,
            $package,
            $therapistId,
            $payable,
            $serviceFee,
            $total,
            &$userPackage,
            &$payment
        ) {
            // 1) نعمل user_package للمستخدم
            $userPackage = UserPackage::create([
                'user_id'        => $user->id,
                'therapist_id'   => $therapistId,
                'package_id'     => $package->id,
                'sessions_total' => $package->sessions_count,
                'sessions_used'  => 0,
                'status'         => 'active', // لو عايزة تربطيها بالدفع خليه pending_payment وحطى check بعدين
                'expires_at'     => $package->validity_days
                    ? now()->addDays($package->validity_days)
                    : null,
                'is_paid'        => true, // لو عايزة تربطيها بـ Paymob خليه false وفعّليه فى الـ webhook
            ]);

            // 2) نعمل Payment مربوط بالـ user_package
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
        });

        return response()->json([
            'data' => [
                'user_package' => $userPackage->load('package','therapist'),
                'payment' => [
                    'id'                => $payment->id,
                    'amount_cents'      => $payment->amount_cents,
                    'currency'          => $payment->currency,
                    'session_fee_cents' => $payable,   // فى شاشة الدفع هيتعرض كـ "سعر الباكيدج"
                    'service_fee_cents' => $serviceFee,
                    'reference'         => $payment->reference,
                ],
            ]
        ], 201);
    }
}
