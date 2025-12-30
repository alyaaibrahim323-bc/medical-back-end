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

   public function index(Request $request)
{
    $with = ['user'];

    if ($request->boolean('with_availability')) {
        $with[] = 'schedules';

    }

    $base = Therapist::with($with)
        ->withCount('sessions');

    $base->when($request->filled('search'), function ($q) use ($request) {
        $s = $request->search;

        $q->where(function ($x) use ($s) {
            $x->whereHas('user', function ($u) use ($s) {
                $u->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            })
            ->orWhere('id', $s);
        });
    });

    $counts = [
        'all'      => (clone $base)->count(),
        'active'   => (clone $base)->where('is_active', 1)->count(),
        'inactive' => (clone $base)->where('is_active', 0)->count(),
    ];

    $base->when($request->filled('active'), function ($q) use ($request) {
        $q->where(
            'is_active',
            filter_var($request->active, FILTER_VALIDATE_BOOLEAN)
        );
    });



    if ($request->boolean('all')) {
        $list = $base->orderByDesc('id')->get();

        return response()->json([
            'data'   => $list,
            'counts' => $counts,
        ]);
    }

    $list = $base->orderByDesc('id')->paginate(20);

    return response()->json([
        'data'   => $list,
        'counts' => $counts,
    ]);
}



    public function show($id)
{
    $t = Therapist::with('user')
        ->withCount('sessions')
        ->findOrFail($id);

    $therapistId = $t->id;

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




    public function destroy($id)
    {
        Therapist::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }



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



   public function schedules($id)
    {
        $therapist = Therapist::findOrFail($id);

        $query = TherapistSchedule::where('therapist_id', $therapist->id);


        if (Schema::hasColumn('therapist_schedules', 'day_of_week')) {
            $query->orderBy('day_of_week')->orderBy('from_time');
        } else {

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



    public function sessions(Request $request, $id)
    {
        $therapist = Therapist::findOrFail($id);

        $q = TherapySession::with(['user','userPackage.package'])
            ->where('therapist_id', $id)
            ->orderByDesc('scheduled_at');


        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }


        if ($request->scope === 'upcoming') {
            $q->where('scheduled_at','>=',now());
        }
        elseif ($request->scope === 'past') {
            $q->where('scheduled_at','<',now());
        }


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


    public function packages($id)
    {
        $rows = Package::where('created_by_therapist_id',$id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $rows,
        ]);
    }



    public function singleSession($id)
    {
        $row = SingleSessionOffer::where('therapist_id',$id)->first();

        return response()->json([
            'data' => $row,
        ]);
    }
}
