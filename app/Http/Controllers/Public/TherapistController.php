<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Therapist;
use App\Models\Package;
use App\Models\SingleSessionOffer;
use App\Services\TherapistAvailabilityService;
use App\Http\Resources\TherapistChatAvailabilityResource;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TherapistController extends Controller
{
    // ============================================
    //  LIST + SEARCH
    // ============================================
    public function index(Request $request)
    {
        $q = Therapist::with('user')
            ->where('is_active', true)

            // SEARCH q= (name OR specialty EN/AR)
            ->when($request->filled('q'), function ($x) use ($request) {
                $kw = '%' . $request->q . '%';

                $x->where(function ($y) use ($kw) {
                    $y->whereHas('user', function ($u) use ($kw) {
                        $u->where('name', 'LIKE', $kw);
                    })
                    ->orWhereRaw('JSON_EXTRACT(specialty, "$.\\"en\\"") LIKE ?', [$kw])
                    ->orWhereRaw('JSON_EXTRACT(specialty, "$.\\"ar\\"") LIKE ?', [$kw]);
                });
            })

            // Filter specialty
            ->when($request->filled('specialty'), function ($x) use ($request) {
                $kw = '%' . $request->specialty . '%';
                $x->where(function ($y) use ($kw) {
                    $y->whereRaw('JSON_EXTRACT(specialty, "$.\\"en\\"") LIKE ?', [$kw])
                      ->orWhereRaw('JSON_EXTRACT(specialty, "$.\\"ar\\"") LIKE ?', [$kw]);
                });
            })

            // Price range
            ->when($request->filled('price_min'), fn ($x) =>
                $x->where('price_cents', '>=', (int) $request->price_min)
            )
            ->when($request->filled('price_max'), fn ($x) =>
                $x->where('price_cents', '<=', (int) $request->price_max)
            )

            // Minimum rating
            ->when($request->filled('rating_min'), fn ($x) =>
                $x->where('rating_avg', '>=', (float) $request->rating_min)
            )

            ->orderByDesc('rating_avg')
            ->orderBy('price_cents');

        // Paginate first
        $paginated = $q->paginate(20);

        // Transform each Therapist into localized structure
        $paginated->setCollection(
            $paginated->getCollection()->map(function (Therapist $t) {
                return [
                    'id' => $t->id,

                    'user' => [
                        'id'     => $t->user?->id,
                        'name'   => $t->user?->name,
                        'email'  => $t->user?->email,
                        'avatar' => $t->user?->avatar,
                    ],

                    'specialty'        => $t->specialtyText,
                    'bio'              => $t->bioText,
                    'price_cents'      => $t->price_cents,
                    'currency'         => $t->currency,
                    'rating_avg'       => $t->rating_avg,
                    'rating_count'     => $t->rating_count,
                    'years_experience' => $t->years_experience,
                    'languages'        => $t->languages,
                    'is_chat_online'   => $t->is_chat_online,
                    'is_active'        => $t->is_active,
                ];
            })
        );

        return response()->json($paginated);
    }

    // ============================================
    //  DETAILS
    // ============================================
    public function show($id)
{
    $t = Therapist::with(['user','chatAvailabilities'])
        ->where('is_active', true)
        ->findOrFail($id);

    $availableChat = $t->chatAvailabilities
        ->where('is_active', true)
        ->sortBy('day_of_week')
        ->values();

    return response()->json([
        'data' => [
            'id' => $t->id,
            'user' => [
                'id'     => $t->user?->id,
                'name'   => $t->user?->name,
                'email'  => $t->user?->email,
                'avatar' => $t->user?->avatar,
            ],
            'specialty'        => $t->specialtyText,
            'bio'              => $t->bioText,
            'price_cents'      => $t->price_cents,
            'currency'         => $t->currency,
            'rating_avg'       => $t->rating_avg,
            'rating_count'     => $t->rating_count,
            'is_active'        => $t->is_active,
            'years_experience' => $t->years_experience,
            'languages'        => $t->languages,

            // ✅ عربي/إنجليزي + label جاهز
            'available_chat'   => TherapistChatAvailabilityResource::collection($availableChat),
        ]
    ]);
}

    // ============================================
    //  AVAILABILITY CALENDAR
    // ============================================
    public function availability($id, Request $request, TherapistAvailabilityService $availability)
    {
        $request->validate([
            'from' => ['nullable','date'],
            'to'   => ['nullable','date','after_or_equal:from'],
            'slot' => ['nullable','integer','min:15','max:240'],
        ]);

        $t = Therapist::where('is_active', true)->findOrFail($id);

        $from = $request->filled('from')
            ? Carbon::parse($request->from)->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse($request->to)->endOfDay()
            : now()->endOfMonth()->endOfDay();

        $slot = (int) ($request->slot ?? 60);

        $days = $availability->slotsForRange($t->id, $from, $to, $slot);

        $normalized = collect($days)->map(function (array $slots, string $date) {
            $carbonDate = Carbon::parse($date);

            return [
                'date'      => $carbonDate->toDateString(),
                'day_name'  => $carbonDate->format('D'),
                'has_slots' => count($slots) > 0,
                'slots'     => collect($slots)->map(function (array $s) {
                    $start = Carbon::parse($s['start']);
                    $end   = Carbon::parse($s['end']);

                    return [
                        'start'        => $start->toIso8601String(),
                        'end'          => $end->toIso8601String(),
                        'duration_min' => $s['duration_min'] ?? $start->diffInMinutes($end),
                        'time_label'   => $start->format('h:i A'),
                    ];
                })->values(),
            ];
        })->values();

        return response()->json(['data' => $normalized]);
    }

    // ============================================
    //  PACKAGES (TAB)
    // ============================================
    public function packages($id)
    {
        $items = Package::where('is_active', true)
            ->where('applicability', 'therapist')
            ->where('created_by_therapist_id', $id)
            ->orderBy('price_cents')
            ->get()
            ->map(function (Package $p) {
                return [
                    'id'                   => $p->id,
                    'name'                 => $p->name_localized,
                    'sessions_count'       => $p->sessions_count,
                    'session_duration_min' => $p->session_duration_min,
                    'price_cents'          => $p->price_cents,
                    'currency'             => $p->currency,
                    'discount_percent'     => $p->discount_percent,
                ];
            });

        return response()->json(['data' => $items]);
    }

    // ============================================
    //  SINGLE SESSION OFFER (TAB)
    // ============================================
    public function singleSession($id)
    {
        $offer = SingleSessionOffer::where('therapist_id', $id)
            ->where('is_active', true)
            ->first();

        if (!$offer) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'price_cents'      => $offer->price_cents,
                'currency'         => $offer->currency,
                'duration_min'     => $offer->duration_min,
                'discount_percent' => $offer->discount_percent,
                'sessions_count'   => 1,
            ]
        ]);
    }
}
