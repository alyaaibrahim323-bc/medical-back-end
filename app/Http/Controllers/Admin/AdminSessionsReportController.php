<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TherapySession;
use Illuminate\Http\Request;

class AdminSessionsReportController extends Controller
{
    public function index(Request $r)
    {
        $q = TherapySession::with(['user','therapist.user','payment'])
            ->when($r->filled('therapist_id'), fn($x)=>$x->where('therapist_id',$r->therapist_id))
            ->when($r->filled('user_id'), fn($x)=>$x->where('user_id',$r->user_id))
            ->when($r->filled('status'), fn($x)=>$x->where('status',$r->status))
            ->when($r->filled('from'), fn($x)=>$x->where('scheduled_at','>=',$r->from))
            ->when($r->filled('to'),   fn($x)=>$x->where('scheduled_at','<=',$r->to))
            ->orderBy('scheduled_at','desc');

        return response()->json(['data'=>$q->paginate(20)]);
    }

    public function show($id)
    {
        $s = TherapySession::with(['user','therapist.user','payment'])->findOrFail($id);
        return response()->json(['data'=>$s]);
    }
}
