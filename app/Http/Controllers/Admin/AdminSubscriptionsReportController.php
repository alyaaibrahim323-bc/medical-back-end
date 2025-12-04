<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserPackage;
use Illuminate\Http\Request;
use App\Http\Resources\UserPackageResource;



class AdminSubscriptionsReportController extends Controller
{
 public function index(Request $r)
{
    // ----- BASE QUERY -----
    $base = UserPackage::query()
        ->when($r->filled('therapist_id'), fn($x)=>$x->where('therapist_id',$r->therapist_id))
        ->when($r->filled('user_id'), fn($x)=>$x->where('user_id',$r->user_id));

    // ----- COUNTS -----
    $counts = [
        'all'       => (clone $base)->count(),
        'active'    => (clone $base)->where('status','active')->count(),
        'completed' => (clone $base)->where('status','completed')->count(),
        'expired'   => (clone $base)->where('status','expired')->count(),
        'pending'   => (clone $base)->where('status','pending')->count(),
    ];

    // ----- LIST -----
    $q = UserPackage::with(['user','package','therapist.user'])
        ->when($r->filled('therapist_id'), fn($x)=>$x->where('therapist_id',$r->therapist_id))
        ->when($r->filled('user_id'), fn($x)=>$x->where('user_id',$r->user_id))
        ->when($r->filled('status'), fn($x)=>$x->where('status',$r->status))
        ->when($r->filled('id'), fn($x)=>$x->where('id', $r->id))
        ->orderByDesc('id');
 $paginated = $q->paginate(20);
    return UserPackageResource::collection($paginated)->additional([
        'counts' => $counts,

    ]);
}

public function show($id)
{
    $p = UserPackage::with(['user','package','therapist.user','redemptions'])
        ->findOrFail($id);

    return response()->json([
        'data' => new UserPackageResource($p)
    ]);
}

}
