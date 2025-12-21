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
        $term = trim((string) $r->query('search', ''));

        $applySearch = function ($q) use ($term) {
            if ($term === '') return;

            $q->where(function ($qq) use ($term) {

                // user name/email/phone
                $qq->whereHas('user', function ($u) use ($term) {
                    $u->where('name', 'like', "%{$term}%")
                      ->orWhere('email', 'like', "%{$term}%")
                      ->orWhere('phone', 'like', "%{$term}%");
                })

                // therapist -> user
                ->orWhereHas('therapist.user', function ($tu) use ($term) {
                    $tu->where('name', 'like', "%{$term}%")
                       ->orWhere('email', 'like', "%{$term}%")
                       ->orWhere('phone', 'like', "%{$term}%");
                })

                // package fields
                ->orWhereHas('package', function ($p) use ($term) {
                    $p->where('name', 'like', "%{$term}%");

                });

                // direct ID if numeric
                if (ctype_digit($term)) {
                    $qq->orWhere('id', (int)$term);
                }
            });
        };

        // base for counts
        $base = UserPackage::query()
            ->when($r->filled('therapist_id'), fn($x) => $x->where('therapist_id', $r->therapist_id))
            ->when($r->filled('user_id'), fn($x) => $x->where('user_id', $r->user_id));

        $applySearch($base);

        $counts = [
            'all'       => (clone $base)->count(),
            'active'    => (clone $base)->where('status', 'active')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'expired'   => (clone $base)->where('status', 'expired')->count(),
            'pending'   => (clone $base)->where('status', 'pending')->count(),
        ];

        // list query
        $q = UserPackage::with(['user','package','therapist.user'])
            ->when($r->filled('therapist_id'), fn($x) => $x->where('therapist_id', $r->therapist_id))
            ->when($r->filled('user_id'), fn($x) => $x->where('user_id', $r->user_id))
            ->when($r->filled('status') && $r->status !== 'all', fn($x) => $x->where('status', $r->status))
            ->when($r->filled('id'), fn($x) => $x->where('id', $r->id));

        $applySearch($q);

        $paginated = $q->orderByDesc('id')->paginate(20);

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
