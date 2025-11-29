<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Therapist;
use App\Models\User;
use App\Models\TherapySession;
use App\Models\TherapistSchedule;
use App\Models\TherapistTimeoff;
use App\Models\Package;
use App\Models\SingleSessionOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TherapistController extends Controller
{
    /**
     * ============================================================
     *  LIST PAGE — WITH COUNTS + SEARCH + ACTIVE FILTER
     * ============================================================
     * GET /admin/therapists?search=&active=true|false
     */
   public function index(Request $request)
{
    $with = ['user'];

    if ($request->boolean('with_availability')) {
        // لازم يكون عندك علاقة schedules على موديل Therapist
        $with[] = 'schedules';
        // ولو حابة كمان timeoffs:
        // $with[] = 'timeoffs';
    }

    $base = Therapist::with($with)
        ->withCount('sessions');

    // 🔍 search
    $base->when($request->filled('search'), function ($q) use ($request) {
        $s = $request->search;

        $q->where(function ($x) use ($s) {
            $x->whereHas('user', function ($u) use ($s) {
                $u->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            })
            ->orWhere('id', $s); // search by therapist_id
        });
    });

    // Active filter
    $base->when($request->filled('active'), function ($q) use ($request) {
        $q->where(
            'is_active',
            filter_var($request->active, FILTER_VALIDATE_BOOLEAN)
        );
    });

    // -----------------------
    // COUNTS FOR DASHBOARD
    // -----------------------
    $counts = [
        'all'      => (clone $base)->count(),
        'active'   => (clone $base)->where('is_active', 1)->count(),
        'inactive' => (clone $base)->where('is_active', 0)->count(),
    ];

    // -----------------------
    // LIST: PAGINATED أو ALL
    // -----------------------

    // لو ?all=1 → رجّع كل الدكاترة بدون paginate
    if ($request->boolean('all')) {
        $list = $base->orderByDesc('id')->get();

        return response()->json([
            'data'   => $list,
            'counts' => $counts,
        ]);
    }

    // الإفتراضي: paginate(20)
    $list = $base->orderByDesc('id')->paginate(20);

    return response()->json([
        'data'   => $list,
        'counts' => $counts,
    ]);
}



    /**
     * ============================================================
     *  SHOW — BASIC INFO + counts (Sessions, Packages, Timeoffs)
     * ============================================================
     * GET /admin/therapists/{id}
     */
    public function show($id)
{
    // نجيب الثيرابست + اليوزر + sessions_count للهيدر
    $t = Therapist::with('user')
        ->withCount('sessions')
        ->findOrFail($id);

    // نستخدم نفس الـ id بتاع الـ Therapist اللي جبناه (مش الـ parameter بس)
    $therapistId = $t->id;

    // Dashboard header numbers
    $counts = [
        'sessions_total'    => TherapySession::where('therapist_id', $therapistId)->count(),
        'sessions_upcoming' => TherapySession::where('therapist_id', $therapistId)
                                    ->where('scheduled_at', '>=', now())
                                    ->count(),
        'sessions_past'     => TherapySession::where('therapist_id', $therapistId)
                                    ->where('scheduled_at', '<', now())
                                    ->count(),
        'packages'          => Package::where('created_by_therapist_id', $therapistId)->count(),
        'timeoffs'          => TherapistTimeoff::where('therapist_id', $therapistId)->count(),
    ];

    return response()->json([
        'data'   => $t,
        'counts' => $counts,
    ]);
}



    /**
     * DELETE /admin/therapists/{id}
     */
    public function destroy($id)
    {
        Therapist::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }


    /**
     * Activate / Deactivate
     * PATCH /admin/therapists/{id}/activate
     */
    public function activate(Request $request, $id)
    {
        $request->validate(['is_active' => ['required','boolean']]);

        $t = Therapist::with('user')->findOrFail($id);
        $t->update(['is_active' => $request->boolean('is_active')]);

        if ($request->boolean('is_active')) {
            $user = $t->user;

            if ($user->role !== 'doctor') {
                $user->role = 'doctor';
                $user->save();
            }
            if (!$user->hasRole('doctor')) {
                $user->syncRoles(['doctor']);
            }
        }

        return response()->json(['data' => $t]);
    }


    /**
     * ============================================================
     *  SCHEDULES TAB
     * ============================================================
     * GET /admin/therapists/{id}/schedules
     */
   public function schedules($id)
    {
        $therapist = Therapist::findOrFail($id);

        $query = TherapistSchedule::where('therapist_id', $therapist->id);

        // ✅ Fix error: Unknown column 'day_of_week'
        // لو عندك عمود day_of_week هنرتّب بيه، لو لأ نرتب بالـ from_time فقط
        if (Schema::hasColumn('therapist_schedules', 'day_of_week')) {
            $query->orderBy('day_of_week')->orderBy('from_time');
        } else {
            // عدلي هنا حسب الأعمدة الموجودة عندك فى الجدول
            if (Schema::hasColumn('therapist_schedules', 'day')) {
                $query->orderBy('day');
            }
            if (Schema::hasColumn('therapist_schedules', 'from_time')) {
                $query->orderBy('from_time');
            } else {
                $query->orderBy('id');
            }
        }

        $rows = $query->get();

        return response()->json([
            'data' => $rows,
        ]);
    }

    /**
     * ============================================================
     *  TIMEOFFS TAB
     * ============================================================
     * GET /admin/therapists/{id}/timeoffs
     */
    public function timeoffs($id)
    {
        $therapist = Therapist::findOrFail($id);

        $rows = TherapistTimeoff::where('therapist_id',$id)
            ->orderByDesc('start_at')
            ->get();

        return response()->json([
            'data' => $rows,
        ]);
    }


    /**
     * ============================================================
     *  SESSIONS TAB + FILTERS
     * ============================================================
     * GET /admin/therapists/{id}/sessions
     */
    public function sessions(Request $request, $id)
    {
        $therapist = Therapist::findOrFail($id);

        $q = TherapySession::with(['user','userPackage.package'])
            ->where('therapist_id', $id)
            ->orderByDesc('scheduled_at');

        // ==========================
        // Status filter
        // ==========================
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        // ==========================
        // scope = upcoming | past
        // ==========================
        if ($request->scope === 'upcoming') {
            $q->where('scheduled_at','>=',now());
        }
        elseif ($request->scope === 'past') {
            $q->where('scheduled_at','<',now());
        }

        // Date range
        if ($request->filled('from')) {
            $q->whereDate('scheduled_at','>=',$request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('scheduled_at','<=',$request->to);
        }

        return response()->json([
            'data' => $q->paginate(20),
        ]);
    }


    /**
     * ============================================================
     *  PACKAGES TAB
     * ============================================================
     * GET /admin/therapists/{id}/packages
     */
    public function packages($id)
    {
        $rows = Package::where('created_by_therapist_id',$id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $rows,
        ]);
    }


    /**
     * ============================================================
     *  SINGLE SESSION OFFER TAB
     * ============================================================
     * GET /admin/therapists/{id}/single-session
     */
    public function singleSession($id)
    {
        $row = SingleSessionOffer::where('therapist_id',$id)->first();

        return response()->json([
            'data' => $row,
        ]);
    }
}
