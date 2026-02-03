<?php
// app/Http/Controllers/Doctor/SingleSessionManageController.php
namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Resources\SingleSessionOfferResource;
use App\Models\SingleSessionOffer;
use Illuminate\Http\Request;

class SingleSessionManageController extends Controller
{
    public function show(Request $r)
    {
        $tid = $r->user()->therapist->id;

        $offer = SingleSessionOffer::with('therapist.user')
            ->where('therapist_id', $tid)
            ->first();

        return $offer
            ? new SingleSessionOfferResource($offer)
            : response()->json(['data' => null]);
    }

    public function store(Request $r)
    {
        $therapist = $r->user()->therapist;
        $tid       = $therapist->id;

        $data = $r->validate([
            // ✅ الجديد
            'price_cents_egp'  => ['nullable','integer','min:0'],
            'price_cents_usd'  => ['nullable','integer','min:0'],

            // ✅ قديم (compat)
            'price_cents'      => ['nullable','integer','min:0'],
            'currency'         => ['nullable','string','size:3'],

            'duration_min'     => ['required','integer','min:15','max:180'],
            'discount_percent' => ['nullable','numeric','min:0','max:100'],
            'is_active'        => ['boolean'],
        ]);

        // ✅ Backward compatibility
        if (!isset($data['price_cents_egp']) && !isset($data['price_cents_usd']) && isset($data['price_cents'])) {
            $cur = strtoupper((string)($data['currency'] ?? 'EGP'));
            if ($cur === 'USD') $data['price_cents_usd'] = (int) $data['price_cents'];
            else $data['price_cents_egp'] = (int) $data['price_cents'];
        }

        $egp = (int)($data['price_cents_egp'] ?? 0);
        $usd = (int)($data['price_cents_usd'] ?? 0);

        if ($egp <= 0 && $usd <= 0) {
            return response()->json([
                'message' => 'You must set at least one price (EGP or USD).'
            ], 422);
        }

        $offer = SingleSessionOffer::updateOrCreate(
            ['therapist_id' => $tid],
            [
                'therapist_id' => $tid,
                'price_cents_egp' => $egp,
                'price_cents_usd' => $usd,

                // legacy fields (اختياري نحدثهم)
                'price_cents' => $egp > 0 ? $egp : $usd,
                'currency' => $egp > 0 ? 'EGP' : 'USD',

                'duration_min' => (int) $data['duration_min'],
                'discount_percent' => (float) ($data['discount_percent'] ?? 0),
                'is_active' => $data['is_active'] ?? true,
            ]
        );

        // لو عندك therapist table بتخزن price/currency، خليها legacy برضه:
        $therapist->update([
            'price_cents' => $egp > 0 ? $egp : $usd,
            'currency'    => $egp > 0 ? 'EGP' : 'USD',
        ]);

        return new SingleSessionOfferResource($offer->load('therapist.user'));
    }


    public function update(Request $r)
{
    $therapist = $r->user()->therapist;
    $tid       = $therapist->id;

    $offer = SingleSessionOffer::where('therapist_id', $tid)->firstOrFail();

    $data = $r->validate([
        'price_cents_egp'  => ['sometimes','nullable','integer','min:0'],
        'price_cents_usd'  => ['sometimes','nullable','integer','min:0'],

        // compat
        'price_cents'      => ['sometimes','nullable','integer','min:0'],
        'currency'         => ['sometimes','nullable','string','size:3'],

        'duration_min'     => ['sometimes','integer','min:15','max:180'],
        'discount_percent' => ['sometimes','numeric','min:0','max:100'],
        'is_active'        => ['sometimes','boolean'],
    ]);

    // compat mapping
    if (!isset($data['price_cents_egp']) && !isset($data['price_cents_usd']) && array_key_exists('price_cents', $data)) {
        $cur = strtoupper((string)($data['currency'] ?? $offer->currency ?? 'EGP'));
        if ($cur === 'USD') $data['price_cents_usd'] = (int) ($data['price_cents'] ?? 0);
        else $data['price_cents_egp'] = (int) ($data['price_cents'] ?? 0);
    }

    // Apply updates safely
    $newEgp = array_key_exists('price_cents_egp', $data) ? (int)($data['price_cents_egp'] ?? 0) : (int)($offer->price_cents_egp ?? 0);
    $newUsd = array_key_exists('price_cents_usd', $data) ? (int)($data['price_cents_usd'] ?? 0) : (int)($offer->price_cents_usd ?? 0);

    if ($newEgp <= 0 && $newUsd <= 0) {
        return response()->json([
            'message' => 'You must keep at least one price (EGP or USD).'
        ], 422);
    }

    $update = [];
    if (array_key_exists('price_cents_egp', $data)) $update['price_cents_egp'] = $newEgp;
    if (array_key_exists('price_cents_usd', $data)) $update['price_cents_usd'] = $newUsd;
    if (array_key_exists('duration_min', $data)) $update['duration_min'] = (int) $data['duration_min'];
    if (array_key_exists('discount_percent', $data)) $update['discount_percent'] = (float) $data['discount_percent'];
    if (array_key_exists('is_active', $data)) $update['is_active'] = (bool) $data['is_active'];

    // legacy sync (اختياري)
    $update['price_cents'] = $newEgp > 0 ? $newEgp : $newUsd;
    $update['currency'] = $newEgp > 0 ? 'EGP' : 'USD';

    $offer->update($update);

    $therapist->update([
        'price_cents' => $update['price_cents'],
        'currency'    => $update['currency'],
    ]);

    return new SingleSessionOfferResource($offer->fresh()->load('therapist.user'));
}


    public function deactivate(Request $r)
    {
        $tid   = $r->user()->therapist->id;
        $offer = SingleSessionOffer::where('therapist_id', $tid)->firstOrFail();

        $offer->update(['is_active' => false]);

        return response()->json(['message' => 'Deactivated']);
    }
}
