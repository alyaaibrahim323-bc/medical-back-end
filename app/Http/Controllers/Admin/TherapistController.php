<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTherapistRequest;
use App\Http\Requests\Admin\UpdateTherapistRequest;
use App\Models\Therapist;
use App\Models\User;
use App\Models\TherapySession;
use App\Models\TherapistSchedule;
use App\Models\TherapistTimeoff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class TherapistController extends Controller
{
    /**
     * Therapists Management list
     * - Search
     * - Filter by active
     * - Total Sessions count
     */
    public function index(Request $request)
    {
        $q = Therapist::with('user')
            // إجمالى عدد الجلسات لكل دكتور
            ->withCount('sessions')

            // 🔍 search by (name / email / phone) from users + therapist id
            ->when($request->filled('search'), function ($x) use ($request) {
                $s = $request->search;

                $x->where(function ($q) use ($s) {
                    $q->whereHas('user', function ($u) use ($s) {
                        $u->where('name', 'like', "%{$s}%")
                          ->orWhere('email', 'like', "%{$s}%")
                          ->orWhere('phone', 'like', "%{$s}%");
                    })
                    ->orWhere('id', $s); // search by therapist_id
                });
            })

            // Active / Inactive filter
            ->when($request->filled('active'), fn($x) =>
                $x->where('is_active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['data' => $q]);
    }


    public function show($id)
    {
        $t = Therapist::with('user')
            ->withCount('sessions') // total sessions for details header
            ->findOrFail($id);

        return response()->json(['data' => $t]);
    }

    public function destroy($id)
    {
        Therapist::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function activate(Request $request, $id)
    {
        $request->validate(['is_active' => ['required','boolean']]);
        $t = Therapist::with('user')->findOrFail($id);
        $t->update(['is_active' => (bool) $request->is_active]);

        // لو فعّلناه نخلي اليوزر دكتور
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
     * Availability tab:
     * GET /admin/therapists/{id}/schedules
     * يعرض Day + Available Time...
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
     * Timeoffs tab:
     * GET /admin/therapists/{id}/timeoffs
     */
    public function timeoffs($id)
    {
        $therapist = Therapist::findOrFail($id);

        $rows = TherapistTimeoff::where('therapist_id', $therapist->id)
            ->orderByDesc('start_at')
            ->get();

        return response()->json([
            'data' => $rows,
        ]);
    }

    /**
     * Performance & Activity tab:
     * - All Sessions / Upcoming / Completed
     * GET /admin/therapists/{id}/sessions?status=&scope=&from=&to=
     */
    public function sessions(Request $request, $id)
    {
        $therapist = Therapist::findOrFail($id);

        $q = TherapySession::with(['user','userPackage.package'])
            ->where('therapist_id', $therapist->id)
            ->orderByDesc('scheduled_at');

        // status: pending_payment / confirmed / completed / cancelled / no_show
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        // scope = upcoming | past (All Session / Upcoming / Completed فى الديزاين)
        if ($request->query('scope') === 'upcoming') {
            $q->where('scheduled_at', '>=', now());
        } elseif ($request->query('scope') === 'past') {
            $q->where('scheduled_at', '<', now());
        }

        // Filter by date range (from / to)
        if ($request->filled('from')) {
            $q->whereDate('scheduled_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('scheduled_at', '<=', $request->to);
        }

        return response()->json([
            'data' => $q->paginate(20),
        ]);
    }

    public function packages($id)
{
    $therapist = Therapist::findOrFail($id);

    $rows = \App\Models\Package::where('created_by_therapist_id', $therapist->id)
        ->orderByDesc('id')
        ->get();

    return response()->json([
        'data' => $rows,
    ]);
}
public function singleSession($id)
{
    $therapist = Therapist::findOrFail($id);

    $row = \App\Models\SingleSessionOffer::where('therapist_id', $therapist->id)->first();

    return response()->json([
        'data' => $row,
    ]);
}

}
