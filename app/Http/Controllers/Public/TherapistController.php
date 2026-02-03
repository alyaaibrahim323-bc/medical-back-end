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
    // =========================
    // Pricing helpers
    // =========================
    private function pricingRegion(Request $r): string
    {
        return $r->user()?->pricing_region ?? 'EG_LOCAL';
    }

    private function isEgypt(Request $r): bool
    {
        return $this->pricingRegion($r) === 'EG_LOCAL';
    }

    private function currencyFor(Request $r): string
    {
        return $this->isEgypt($r) ? 'EGP' : 'USD';
    }

    private function offerPriceColumn(Request $r): string
    {
        // column name used in SQL (JOIN alias sso)
        return $this->isEgypt($r) ? 'sso.price_cents_egp' : 'sso.price_cents_usd';
    }

    // =========================
    // Public list therapists
    // =========================
    public function index(Request $request)
    {
        $priceCol = $this->offerPriceColumn($request);
        $currency = $this->currencyFor($request);
        $isEgypt  = $this->isEgypt($request);

        $q = Therapist::with(['user', 'activeSingleSessionOffer'])
            ->where('therapists.is_active', true)

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

            ->when($request->filled('specialty'), function ($x) use ($request) {
                $kw = '%' . $request->specialty . '%';
                $x->where(function ($y) use ($kw) {
                    $y->whereRaw('JSON_EXTRACT(specialty, "$.\\"en\\"") LIKE ?', [$kw])
                      ->orWhereRaw('JSON_EXTRACT(specialty, "$.\\"ar\\"") LIKE ?', [$kw]);
                });
            })

            // join offer for filtering/sorting by price
            ->leftJoin('single_session_offers as sso', function ($join) {
                $join->on('sso.therapist_id', '=', 'therapists.id')
                    ->where('sso.is_active', true);
            })
            ->select('therapists.*')

            // ✅ price filters now use correct column per region
            ->when($request->filled('price_min'), fn ($x) =>
                $x->where($priceCol, '>=', (int) $request->price_min)
            )
            ->when($request->filled('price_max'), fn ($x) =>
                $x->where($priceCol, '<=', (int) $request->price_max)
            )

            ->when($request->filled('rating_min'), fn ($x) =>
                $x->where('therapists.rating_avg', '>=', (float) $request->rating_min)
            )

            ->orderByDesc('therapists.rating_avg')
            // ✅ sorting by region-based price column
            ->orderBy($priceCol);

        $paginated = $q->paginate(20);

        $paginated->setCollection(
            $paginated->getCollection()->map(function (Therapist $t) use ($isEgypt, $currency) {

                $offer = $t->activeSingleSessionOffer;

                // ✅ choose offer price by region
                $offerPrice = 0;
                if ($offer) {
                    $offerPrice = (int) ($isEgypt ? ($offer->price_cents_egp ?? 0) : ($offer->price_cents_usd ?? 0));
                }

                // legacy fallback
                if ($offerPrice <= 0) {
                    $offerPrice = (int) ($t->price_cents ?? 0);
                }

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

                    // ✅ still return same fields (Flutter-safe)
                    'price_cents'      => $offerPrice,
                    'currency'         => $currency,

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

    // =========================
    // Public therapist details
    // =========================
    public function show(Request $r, $id)
    {
        $isEgypt  = $this->isEgypt($r);
        $currency = $this->currencyFor($r);

        $t = Therapist::with(['user','chatAvailabilities'])
            ->where('is_active', true)
            ->findOrFail($id);

        $availableChat = $t->chatAvailabilities
            ->where('is_active', true)
            ->sortBy('day_of_week')
            ->values();

        $offer = SingleSessionOffer::where('therapist_id', $t->id)
            ->where('is_active', true)
            ->first();

        $price = 0;
        if ($offer) {
            $price = (int) ($isEgypt ? ($offer->price_cents_egp ?? 0) : ($offer->price_cents_usd ?? 0));
        }
        if ($price <= 0) {
            $price = (int) ($t->price_cents ?? 0);
        }

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

                // ✅ region-based price
                'price_cents'      => $price,
                'currency'         => $currency,

                'rating_avg'       => $t->rating_avg,
                'rating_count'     => $t->rating_count,
                'is_active'        => $t->is_active,
                'years_experience' => $t->years_experience,
                'languages'        => $t->languages,

                'available_chat'   => TherapistChatAvailabilityResource::collection($availableChat),
            ]
        ]);
    }

    // =========================
    // Availability (unchanged)
    // =========================
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

    // =========================
    // Therapist packages (region-based price)
    // =========================
    public function packages(Request $r, $id)
    {
        $isEgypt  = $this->isEgypt($r);
        $currency = $this->currencyFor($r);
        $orderCol = $isEgypt ? 'price_cents_egp' : 'price_cents_usd';

        $items = Package::where('is_active', true)
            ->where('applicability', 'therapist')
            ->where('created_by_therapist_id', $id)
            ->orderBy($orderCol)
            ->get()
            ->map(function (Package $p) use ($isEgypt, $currency) {

                $price = (int) ($isEgypt ? ($p->price_cents_egp ?? 0) : ($p->price_cents_usd ?? 0));
                if ($price <= 0) $price = (int) ($p->price_cents ?? 0); // legacy fallback

                return [
                    'id'                   => $p->id,
                    'name'                 => $p->name_localized,
                    'sessions_count'       => $p->sessions_count,
                    'session_duration_min' => $p->session_duration_min,
                    'price_cents'          => $price,
                    'currency'             => $currency,
                    'discount_percent'     => $p->discount_percent,
                ];
            });

        return response()->json(['data' => $items]);
    }

    // =========================
    // Single session offer (region-based price)
    // =========================
    public function singleSession(Request $r, $id)
    {
        $isEgypt  = $this->isEgypt($r);
        $currency = $this->currencyFor($r);

        $offer = SingleSessionOffer::where('therapist_id', $id)
            ->where('is_active', true)
            ->first();

        if (!$offer) {
            return response()->json(['data' => null]);
        }

        $price = (int) ($isEgypt ? ($offer->price_cents_egp ?? 0) : ($offer->price_cents_usd ?? 0));

        if ($price <= 0) {
            return response()->json([
                'message' => 'Pricing not set for your region',
                'data' => null
            ], 422);
        }

        return response()->json([
            'data' => [
                'price_cents'      => $price,
                'currency'         => $currency,
                'duration_min'     => $offer->duration_min,
                'discount_percent' => $offer->discount_percent,
                'sessions_count'   => 1,
            ]
        ]);
    }
}
