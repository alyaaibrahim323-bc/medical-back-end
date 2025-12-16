<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;

class DoctorPackagesController extends Controller
{
public function index(Request $r)
{
    $therapistId = auth()->user()->therapist->id;

    // ✅ Search helper
    $applySearch = function ($q) use ($r) {
        if (!$r->filled('search')) return;

        $term = trim($r->search);

        $q->where(function ($qq) use ($term) {
            $qq->where('name', 'like', "%{$term}%")
               ->orWhere('description', 'like', "%{$term}%")
               ->orWhere('id', $term);
        });
    };

    // 👈 base query + عدد اليوزرز في كل باكيدج
    $base = Package::ownedByDoctor($therapistId)
        ->withCount('userPackages');

    // ✅ apply search to base so counts affected
    $applySearch($base);

    // ✅ الأعداد لكل تاب (هتتأثر بالـ search)
    $counts = [
        'all'      => (clone $base)->count(),
        'active'   => (clone $base)->where('is_active', true)->count(),
        'inactive' => (clone $base)->where('is_active', false)->count(),
    ];

    // ----- LIST -----
    $q = clone $base;

    // active filter (تاب active/inactive)
    $q->when(
        $r->filled('active'),
        fn($x) => $x->where(
            'is_active',
            filter_var($r->active, FILTER_VALIDATE_BOOLEAN)
        )
    );

    // (اختياري) لو مش حابة search يتطبق مرتين بسبب clone، سيبيه — هنا clone واخد search بالفعل.
    // لو شيلتي clone وبنيتي q من جديد، ساعتها لازم $applySearch($q)

    $q->orderByDesc('id');

    $list = $q->paginate(20);

    return response()->json([
        'data'   => $list,
        'counts' => $counts,
    ]);
}




    public function store(Request $r)
    {
        $therapistId = auth()->user()->therapist->id;

        $data = $r->validate([
            'name'                 => ['required'],
            'description'          => ['nullable'],
            'sessions_count'       => ['required','integer','min:1','max:100'],
            'session_duration_min' => ['required','integer','min:15','max:180'],
            'price_cents'          => ['required','integer','min:0'],
            'discount_percent'     => ['nullable','numeric','min:0','max:100'],
            'currency'             => ['required','string','size:3'],
            'validity_days'        => ['nullable','integer','min:1'],
            'is_active'            => ['boolean'],
        ]);

        foreach (['name','description'] as $f) if (isset($data[$f]) && is_string($data[$f])) $data[$f] = ['en'=>$data[$f]];

        $p = Package::create($data + [
            'applicability'            => 'therapist',
            'created_by_therapist_id'  => $therapistId,
            'is_active'                => $data['is_active'] ?? true,
        ]);

        return response()->json(['data'=>$p], 201);
    }

    public function show($id)
    {
        $therapistId = auth()->user()->therapist->id;
        $p = Package::ownedByDoctor($therapistId)->findOrFail($id);
        return response()->json(['data'=>$p]);
    }

    public function update(Request $r, $id)
    {
        $therapistId = auth()->user()->therapist->id;
        $p = Package::ownedByDoctor($therapistId)->findOrFail($id);

        $data = $r->validate([
            'name'                 => ['sometimes'],
            'description'          => ['sometimes'],
            'sessions_count'       => ['sometimes','integer','min:1','max:100'],
            'session_duration_min' => ['sometimes','integer','min:15','max:180'],
            'price_cents'          => ['sometimes','integer','min:0'],
            'discount_percent'     => ['sometimes','numeric','min:0','max:100'],
            'currency'             => ['sometimes','string','size:3'],
            'validity_days'        => ['sometimes','nullable','integer','min:1'],
            'is_active'            => ['sometimes','boolean'],
        ]);

        foreach (['name','description'] as $f) if (array_key_exists($f,$data) && is_string($data[$f])) $data[$f] = ['en'=>$data[$f]];

        $p->update($data);
        return response()->json(['data'=>$p->refresh()]);
    }

    public function destroy($id)
    {
        $therapistId = auth()->user()->therapist->id;
        $p = Package::ownedByDoctor($therapistId)->findOrFail($id);
        $p->delete();
        return response()->json(['message'=>'Deleted']);
    }
}
