<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TherapySession;
use Illuminate\Http\Request;

class AdminSessionsReportController extends Controller
{
public function index(Request $r)
{
    // -------- SEARCH HELPER (Reusable) --------
    $applySearch = function ($q) use ($r) {
        if (!$r->filled('search')) return;

        $term = trim($r->search);

        $q->where(function ($qq) use ($term) {
            $qq->whereHas('user', function ($u) use ($term) {
                    $u->where('name', 'like', "%{$term}%")
                      ->orWhere('email', 'like', "%{$term}%")
                      ->orWhere('phone', 'like', "%{$term}%");
                })
              ->orWhereHas('therapist.user', function ($tu) use ($term) {
                    $tu->where('name', 'like', "%{$term}%")
                       ->orWhere('email', 'like', "%{$term}%")
                       ->orWhere('phone', 'like', "%{$term}%");
                })
              // لو بتبحثي برقم السيشن
              ->orWhere('id', $term);
        });
    };

    // -------- BASE QUERY (for counts) --------
    $base = TherapySession::query()
        ->when($r->filled('therapist_id'), fn($x) => $x->where('therapist_id', $r->therapist_id))
        ->when($r->filled('user_id'), fn($x) => $x->where('user_id', $r->user_id))
        ->when($r->filled('from'), fn($x) => $x->where('scheduled_at', '>=', $r->from))
        ->when($r->filled('to'),   fn($x) => $x->where('scheduled_at', '<=', $r->to));

    // ✅ search affects counts
    $applySearch($base);

    // -------- COUNTS (tabs) --------
    $counts = [
        'all'        => (clone $base)->count(),
        'completed'  => (clone $base)->where('status', 'completed')->count(),
        'cancelled'  => (clone $base)->where('status', 'cancelled')->count(),
        'pending'    => (clone $base)->where('status', 'pending_payment')->count(),
        'confirmed'  => (clone $base)->where('status', 'confirmed')->count(),
    ];

    // -------- LIST QUERY (for data) --------
    $q = TherapySession::with(['user', 'therapist.user', 'payment'])
        ->when($r->filled('therapist_id'), fn($x) => $x->where('therapist_id', $r->therapist_id))
        ->when($r->filled('user_id'), fn($x) => $x->where('user_id', $r->user_id))
        ->when($r->filled('status'), fn($x) => $x->where('status', $r->status))
        ->when($r->filled('from'), fn($x) => $x->where('scheduled_at', '>=', $r->from))
        ->when($r->filled('to'),   fn($x) => $x->where('scheduled_at', '<=', $r->to));

    // ✅ same search affects list
    $applySearch($q);

    $q->orderByDesc('created_at');

    return response()->json([
        'data'   => $q->paginate(20),
        'counts' => $counts,
    ]);
}


public function show($id)
{
    $s = TherapySession::with(['user','therapist.user','payment'])->findOrFail($id);
    return response()->json(['data'=>$s]);
}

}
