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

    $applySearch = function ($q) use ($r) {
        if (!$r->filled('search')) return;

        $term = trim($r->search);

        $q->where(function ($qq) use ($term) {

            $qq->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) LIKE ?",
                ["%{$term}%"]
            )
            ->orWhereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(name, '$.ar')) LIKE ?",
                ["%{$term}%"]
            )


            ->orWhereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(description, '$.en')) LIKE ?",
                ["%{$term}%"]
            )
            ->orWhereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(description, '$.ar')) LIKE ?",
                ["%{$term}%"]
            );

            if (ctype_digit($term)) {
                $qq->orWhere('id', (int)$term);
            }
        });
    };


    $base = Package::ownedByDoctor($therapistId)
        ->withCount('userPackages');


    $applySearch($base);


    $counts = [
        'all'      => (clone $base)->count(),
        'active'   => (clone $base)->where('is_active', true)->count(),
        'inactive' => (clone $base)->where('is_active', false)->count(),
    ];

    $q = clone $base;


    $q->when(
        $r->filled('active'),
        fn($x) => $x->where(
            'is_active',
            filter_var($r->active, FILTER_VALIDATE_BOOLEAN)
        )
    );

    $q->orderByDesc('id');

    return response()->json([
        'data'   => $q->paginate(20),
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
             // الجديد
            'price_cents_egp'      => ['nullable','integer','min:0'],
            'price_cents_usd'      => ['nullable','integer','min:0'],

            'price_cents'          => ['required','integer','min:0'],
            'discount_percent'     => ['nullable','numeric','min:0','max:100'],
            'currency'             => ['required','string','size:3'],
            'validity_days'        => ['nullable','integer','min:1'],
            'is_active'            => ['boolean'],
        ]);
        // compat
        if (!isset($data['price_cents_egp']) && !isset($data['price_cents_usd']) && isset($data['price_cents'])) {
            $cur = strtoupper((string)($data['currency'] ?? 'EGP'));
            if ($cur === 'USD') $data['price_cents_usd'] = (int) $data['price_cents'];
            else $data['price_cents_egp'] = (int) $data['price_cents'];
        }

        $egp = (int)($data['price_cents_egp'] ?? 0);
        $usd = (int)($data['price_cents_usd'] ?? 0);

        if ($egp <= 0 && $usd <= 0) {
            return response()->json(['message' => 'You must set at least one price (EGP or USD).'], 422);
        }

        // legacy sync (اختياري)
        $data['price_cents'] = $egp > 0 ? $egp : $usd;
        $data['currency'] = $egp > 0 ? 'EGP' : 'USD';

        // ثبت الجديد
        $data['price_cents_egp'] = $egp;
        $data['price_cents_usd'] = $usd;

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
            'price_cents_egp'      => ['sometimes','nullable','integer','min:0'],
           'price_cents_usd'      => ['sometimes','nullable','integer','min:0'],
            'price_cents'          => ['sometimes','integer','min:0'],
            'discount_percent'     => ['sometimes','numeric','min:0','max:100'],
            'currency'             => ['sometimes','string','size:3'],
            'validity_days'        => ['sometimes','nullable','integer','min:1'],
            'is_active'            => ['sometimes','boolean'],
        ]);
        // compat mapping
        if (!isset($data['price_cents_egp']) && !isset($data['price_cents_usd']) && array_key_exists('price_cents',$data)) {
            $cur = strtoupper((string)($data['currency'] ?? $p->currency ?? 'EGP'));
            if ($cur === 'USD') $data['price_cents_usd'] = (int) ($data['price_cents'] ?? 0);
            else $data['price_cents_egp'] = (int) ($data['price_cents'] ?? 0);
        }

        $newEgp = array_key_exists('price_cents_egp', $data) ? (int)($data['price_cents_egp'] ?? 0) : (int)($p->price_cents_egp ?? 0);
        $newUsd = array_key_exists('price_cents_usd', $data) ? (int)($data['price_cents_usd'] ?? 0) : (int)($p->price_cents_usd ?? 0);

        if ($newEgp <= 0 && $newUsd <= 0) {
            return response()->json(['message' => 'You must keep at least one price (EGP or USD).'], 422);
        }

        // legacy sync optional
        $data['price_cents'] = $newEgp > 0 ? $newEgp : $newUsd;
        $data['currency'] = $newEgp > 0 ? 'EGP' : 'USD';

        // store new fields if present
        if (array_key_exists('price_cents_egp',$data)) $data['price_cents_egp'] = $newEgp;
        if (array_key_exists('price_cents_usd',$data)) $data['price_cents_usd'] = $newUsd;

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
