<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;

class PackagesController extends Controller
{
    public function index(Request $r)
    {
        $q = Package::where('is_active',true)
            ->when($r->filled('applicability'), fn($x)=>$x->where('applicability',$r->applicability))
            ->orderBy('price_cents');

        return response()->json(['data'=>$q->paginate(20)]);
    }

    public function show($id)
    {
        $p = Package::where('is_active', true)->findOrFail($id);
        return response()->json(['data'=>$p]);
    }
}
