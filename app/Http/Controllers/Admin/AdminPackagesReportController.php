<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;

class AdminPackagesReportController extends Controller
{
    public function index(Request $r)
    {
        $q = Package::query()
            ->when($r->filled('therapist_id'), fn($x)=>$x->where('created_by_therapist_id', $r->therapist_id))
            ->when($r->filled('active'), fn($x)=>$x->where('is_active', filter_var($r->active, FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('id');

        return response()->json(['data'=>$q->paginate(20)]);
    }

    public function show($id)
    {
        $p = Package::findOrFail($id);
        return response()->json(['data'=>$p]);
    }
}
