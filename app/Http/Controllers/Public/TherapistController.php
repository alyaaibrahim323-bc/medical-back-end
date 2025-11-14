<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Therapist;
use App\Models\Package;
use App\Models\SingleSessionOffer;
use App\Services\TherapistAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TherapistController extends Controller
{
    // ============ LIST + SEARCH (شاشة list أو search) ============
    public function index(Request $request)
    {
        $q = Therapist::with('user')
            ->where('is_active', true)

            // سيرش عام Doctor or Specialty --> ?q=
            ->when($request->filled('q'), function ($x) use ($request) {
                $kw = '%' . $request->q . '%';

                $x->where(function ($y) use ($kw) {
                    // بالاسم من جدول users
                    $y->whereHas('user', function ($u) use ($kw) {
                        $u->where('name', 'LIKE', $kw);
                    })
                    // أو بالتخصص EN/AR من JSON specialty
                    ->orWhereRaw('JSON_EXTRACT(specialty, "$.\\"en\\"") LIKE ?', [$kw])
                    ->orWhereRaw('JSON_EXTRACT(specialty, "$.\\"ar\\"") LIKE ?', [$kw]);
                });
            })

            // فلتر تخصص لوحده (اختياري)
            ->when($request->filled('specialty'), function ($x) use ($request) {
                $kw = '%' . $request->specialty . '%';
                $x->where(function ($y) use ($kw) {
                    $y->whereRaw('JSON_EXTRACT(specialty, "$.\\"en\\"") LIKE ?', [$kw])
                      ->orWhereRaw('JSON_EXTRACT(specialty, "$.\\"ar\\"") LIKE ?', [$kw]);
                });
            })

            // السعر min/max
            ->when($request->filled('price_min'), fn ($x) =>
                $x->where('price_cents', '>=', (int) $request->price_min)
            )
            ->when($request->filled('price_max'), fn ($x) =>
                $x->where('price_cents', '<=', (int) $request->price_max)
            )

            // أقل تقييم
            ->when($request->filled('rating_min'), fn ($x) =>
                $x->where('rating_avg', '>=', (float) $request->rating_min)
            )

            ->orderByDesc('rating_avg')
            ->orderBy('price_cents');

        return response()->json(['data' => $q->paginate(20)]);
    }

    // ============ Therapist Details (الهيدر + About) ============
    public function show($id)
    {
        $t = Therapist::with('user')
            ->where('is_active', true)
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'id'           => $t->id,
                'user'         => $t->user,           // name, avatar, email...
                'specialty'    => $t->specialtyText,  // accessor بيترجم EN/AR
                'bio'          => $t->bioText,
                'price_cents'  => $t->price_cents,
                'currency'     => $t->currency,
                'rating_avg'   => $t->rating_avg,
                'rating_count' => $t->rating_count,
                'is_active'    => $t->is_active,
                'years_experience' => $t->years_experience,
                'languages'    => $t->languages,
                // لو عندك أعمدة للـ Available Chat زوديها هنا
                'available_chat_from' => $t->available_chat_from ?? null,
                'available_chat_to'   => $t->available_chat_to ?? null,
            ]
        ]);
    }

    // ============ Availability (لشاشة Date & Time) ============
    public function availability($id, Request $request, TherapistAvailabilityService $availability)
    {
        $request->validate([
            'from' => ['required','date'],
            'to'   => ['required','date','after_or_equal:from'],
            'slot' => ['nullable','integer','min:15','max:240'],
        ]);

        $t = Therapist::where('is_active', true)->findOrFail($id);

        $from = Carbon::parse($request->from)->startOfDay();
        $to   = Carbon::parse($request->to)->endOfDay();
        $slot = (int) ($request->slot ?? 60);

        $days = $availability->slotsForRange($t->id, $from, $to, $slot);

        return response()->json(['data' => $days]);
    }

    // ============ Packages TAB (أول شاشة في الصورة) ============
    public function packages($id, Request $request)
    {
        // باكيدجات هذا الدكتور فقط – Active
        $q = Package::where('is_active', true)
            ->where('applicability', 'therapist')
            ->where('created_by_therapist_id', $id)
            ->orderBy('price_cents');

        $items = $q->get()->map(function (Package $p) {
            return [
                'id'                 => $p->id,
                'name'               => $p->name_localized ?? $p->name,   // حسب الموديل عندك
                'sessions_count'     => $p->sessions_count,
                'session_duration_min'=> $p->session_duration_min,
                'price_cents'        => $p->price_cents,
                'currency'           => $p->currency,
                'discount_percent'   => $p->discount_percent,
            ];
        });

        return response()->json(['data' => $items]);
    }

    // ============ Single Session TAB (الشاشة الوسطى) ============
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
                'sessions_count'   => 1, // single session
            ]
        ]);
    }
}
