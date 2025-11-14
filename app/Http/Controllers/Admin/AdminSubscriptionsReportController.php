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
    $q = \App\Models\UserPackage::with(['user','package','therapist.user'])
        ->when($r->filled('therapist_id'), fn($x)=>$x->where('therapist_id',$r->therapist_id))
        ->when($r->filled('user_id'), fn($x)=>$x->where('user_id',$r->user_id))
        ->when($r->filled('status'), fn($x)=>$x->where('status',$r->status))
        ->orderByDesc('id');

    return UserPackageResource::collection($q->paginate(20));
}

public function show($id)
{
    $p = \App\Models\UserPackage::with(['user','package','therapist.user','redemptions'])
        ->findOrFail($id);

    return new UserPackageResource($p);
}
}
