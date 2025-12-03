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

    // 👈 base query (package_id + q فقط)
    $base = UserPackage::query()
        ->with([
            'user:id,name,email,phone',
            'package:id,name,sessions_count,session_duration_min,price_cents,currency',
            'therapist:id,user_id',
            'redemptions:id,user_package_id,therapy_session_id,redeemed_at'
        ])
        ->where('therapist_id', $therapistId)
        ->when($r->filled('package_id'), fn($x) => $x->where('package_id', $r->integer('package_id')))
        ->when($r->filled('q'), function ($x) use ($r) {
            $kw = '%'.trim($r->q).'%';
            $x->whereHas('user', fn($u) =>
                $u->where('name','like',$kw)->orWhere('email','like',$kw)
            );
        });

    // ✅ الأعداد (كلها / Active / Inactive) على أساس status
    $counts = [
        'all'      => (clone $base)->count(),
        'active'   => (clone $base)->where('status', 'active')->count(),
        'inactive' => (clone $base)->where('status', '!=', 'active')->count(),
    ];

    // 👇 نطبّق فلتر active حسب التاب المفتوح
    $q = clone $base;

    $q->when($r->filled('active'), function ($x) use ($r) {
        $bool = filter_var($r->active, FILTER_VALIDATE_BOOLEAN);

        if ($bool) {
            // لو ?active=true → رجّع اللى status=active
            $x->where('status', 'active');
        } else {
            // لو ?active=false → رجّع أى حاجة غير active
            $x->where('status', '!=', 'active');
        }
    })
    ->orderByDesc('id');

    $paginated = $q->paginate(20);

    return UserPackageResource::collection($paginated)->additional([
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
