<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserPackageResource;
use App\Models\PackageRedemption;
use App\Models\UserPackage;
use Illuminate\Http\Request;

class DoctorClientsController extends Controller
{
    // قائمة كل المشتركين في باكيدجات هذا الدكتور
    // فلاتر اختيارية: ?package_id= & ?active=true/false & ?q=اسم_العميل/الإيميل
public function subscriptions(Request $r)
{
    $therapistId = $r->user()->therapist->id;

    // ✅ Search helper (using ?search=)
    $applySearch = function ($q) use ($r) {
        if (!$r->filled('search')) return;

        $term = trim((string) $r->search);
        $kw   = "%{$term}%";

        $q->where(function ($qq) use ($term, $kw) {

            // 1) user name / email / phone
            $qq->whereHas('user', function ($u) use ($kw) {
                $u->where('name', 'like', $kw)
                  ->orWhere('email', 'like', $kw)
                  ->orWhere('phone', 'like', $kw);
            })

            // 2) package name (JSON en/ar)
            ->orWhereHas('package', function ($p) use ($kw) {
                $p->whereRaw(
                        "JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) LIKE ?",
                        [$kw]
                    )
                  ->orWhereRaw(
                        "JSON_UNQUOTE(JSON_EXTRACT(name, '$.ar')) LIKE ?",
                        [$kw]
                    );
            });

            // 3) direct id search
            if (ctype_digit($term)) {
                $qq->orWhere('id', (int) $term);
            }
        });
    };

    $base = UserPackage::query()
        ->with([
            'user:id,name,email,phone',
            'package:id,name,sessions_count,session_duration_min,price_cents,currency',
            'therapist:id,user_id',
            'redemptions:id,user_package_id,therapy_session_id,redeemed_at'
        ])
        ->where('therapist_id', $therapistId)
        ->when(
            $r->filled('package_id'),
            fn($x) => $x->where('package_id', $r->integer('package_id'))
        );

    // ✅ search affects counts
    $applySearch($base);

    // ✅ counts
    $counts = [
        'all'     => (clone $base)->count(),
        'active'  => (clone $base)->where('status', 'active')->count(),
        'expired' => (clone $base)->where('status', 'expired')->count(),
    ];

    // ✅ list
    $q = clone $base;

    $q->when($r->filled('status'), function ($x) use ($r) {
        if (in_array($r->status, ['active', 'expired'], true)) {
            $x->where('status', $r->status);
        }
    })->orderByDesc('id');

    return UserPackageResource::collection(
        $q->paginate(20)
    )->additional([
        'counts' => $counts,
    ]);
}





    // تفاصيل اشتراك محدد
    public function subscriptionShow(Request $r, int $id)
    {
        $therapistId = $r->user()->therapist->id;

        $p = UserPackage::with([
                'user:id,name,email,phone',
                'package:id,name,sessions_count,session_duration_min,price_cents,currency',
                'redemptions:id,user_package_id,therapy_session_id,redeemed_at'
            ])
            ->where('therapist_id', $therapistId)
            ->findOrFail($id);

        return new UserPackageResource($p);
    }

    // الجلسات التي استُخدمت من هذا الاشتراك (مع بعض البيانات)
    public function subscriptionSessions(Request $r, int $id)
    {
        $therapistId = $r->user()->therapist->id;

        $up = UserPackage::where('therapist_id',$therapistId)->findOrFail($id);

        $rows = PackageRedemption::with(['therapySession.user:id,name'])
            ->where('user_package_id', $up->id)
            ->orderByDesc('redeemed_at')
            ->get()
            ->map(function($r){
                return [
                    'session_id'   => $r->therapy_session_id,
                    'client_name'  => optional($r->therapySession?->user)->name,
                    'scheduled_at' => optional($r->therapySession)->scheduled_at,
                    'status'       => optional($r->therapySession)->status,
                    'redeemed_at'  => $r->redeemed_at,
                ];
            });

        return response()->json(['data'=>$rows]);
    }
}
