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
        $therapist = $r->user()->therapist; // عشان نحدّث جدول therapists
        $tid       = $therapist->id;

        $data = $r->validate([
            'price_cents'      => ['required','integer','min:0'],
            'currency'         => ['required','string','size:3'],
            'duration_min'     => ['required','integer','min:15','max:180'],
            'discount_percent' => ['nullable','numeric','min:0','max:100'],
            'is_active'        => ['boolean'],
        ]);

        // 1) نحفظ / نحدّث العرض في جدول single_session_offers
        $offer = SingleSessionOffer::updateOrCreate(
            ['therapist_id' => $tid],
            $data + ['is_active' => $data['is_active'] ?? true]
        );

        // 2) نحدّث نفس السعر والعملة في جدول therapists (ده اللي إنتِ عايزاه)
        $therapist->update([
            'price_cents' => $data['price_cents'],
            'currency'    => $data['currency'],
        ]);

        return new SingleSessionOfferResource($offer->load('therapist.user'));
    }

    public function update(Request $r)
    {
        $therapist = $r->user()->therapist;
        $tid       = $therapist->id;

        $offer = SingleSessionOffer::where('therapist_id', $tid)->firstOrFail();

        $data = $r->validate([
            'price_cents'      => ['sometimes','integer','min:0'],
            'currency'         => ['sometimes','string','size:3'],
            'duration_min'     => ['sometimes','integer','min:15','max:180'],
            'discount_percent' => ['sometimes','numeric','min:0','max:100'],
            'is_active'        => ['sometimes','boolean'],
        ]);

        // 1) نحدّث الـ offer
        $offer->update($data);

        // 2) لو السعر/العملة اتبعتوا في الريكوست → عدلهم في therapists برضه
        $therapistUpdate = [];

        if (isset($data['price_cents'])) {
            $therapistUpdate['price_cents'] = $data['price_cents'];
        }

        if (isset($data['currency'])) {
            $therapistUpdate['currency'] = $data['currency'];
        }

        if (!empty($therapistUpdate)) {
            $therapist->update($therapistUpdate);
        }

        return new SingleSessionOfferResource(
            $offer->fresh()->load('therapist.user')
        );
    }

    public function deactivate(Request $r)
    {
        $tid   = $r->user()->therapist->id;
        $offer = SingleSessionOffer::where('therapist_id', $tid)->firstOrFail();

        $offer->update(['is_active' => false]);

        return response()->json(['message' => 'Deactivated']);
    }
}
