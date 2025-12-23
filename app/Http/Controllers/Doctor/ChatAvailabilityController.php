<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Resources\TherapistChatAvailabilityResource;
use App\Models\TherapistChatAvailability;
use Illuminate\Http\Request;

class ChatAvailabilityController extends Controller
{
  
    public function index(Request $r)
    {
        $t = $r->user()->therapist()->firstOrFail();

        $items = TherapistChatAvailability::where('therapist_id', $t->id)
            ->orderBy('day_of_week')
            ->get();

        return response()->json([
            'data' => TherapistChatAvailabilityResource::collection($items),
        ]);
    }

    public function store(Request $r)
    {
        $t = $r->user()->therapist()->firstOrFail();

        $data = $r->validate([
            'days'        => ['required','array','min:1'],
            'days.*'      => ['integer','min:0','max:6'],
            'from_time'   => ['required','date_format:H:i'],
            'to_time'     => ['required','date_format:H:i','after:from_time'],
            'is_active'   => ['sometimes','boolean'],
        ]);

        $isActive = $data['is_active'] ?? true;

        foreach (array_unique($data['days']) as $day) {
            TherapistChatAvailability::updateOrCreate(
                ['therapist_id' => $t->id, 'day_of_week' => (int)$day],
                ['from_time' => $data['from_time'], 'to_time' => $data['to_time'], 'is_active' => $isActive]
            );
        }

        $items = TherapistChatAvailability::where('therapist_id', $t->id)
            ->orderBy('day_of_week')
            ->get();

        return response()->json([
            'message' => 'Chat availability saved',
            'data'    => TherapistChatAvailabilityResource::collection($items),
        ]);
    }


    public function update(Request $r, int $day)
    {
        $t = $r->user()->therapist()->firstOrFail();
        abort_if($day < 0 || $day > 6, 404);

        $data = $r->validate([
            'from_time' => ['required','date_format:H:i'],
            'to_time'   => ['required','date_format:H:i','after:from_time'],
            'is_active' => ['sometimes','boolean'],
        ]);

        $row = TherapistChatAvailability::where('therapist_id', $t->id)
            ->where('day_of_week', $day)
            ->firstOrFail();

        $row->update($data);

        return response()->json([
            'message' => 'Updated',
            'data'    => new TherapistChatAvailabilityResource($row),
        ]);
    }

    public function destroy(Request $r, int $day)
    {
        $t = $r->user()->therapist()->firstOrFail();
        abort_if($day < 0 || $day > 6, 404);

        TherapistChatAvailability::where('therapist_id', $t->id)
            ->where('day_of_week', $day)
            ->delete();

        return response()->json(['message' => 'Deleted']);
    }

   
    public function replace(Request $r)
{
    $t = $r->user()->therapist()->firstOrFail();

    $data = $r->validate([
        'days'      => ['required','array','min:1'],
        'days.*'    => ['integer','min:0','max:6'],
        'from_time' => ['required','date_format:H:i'],
        'to_time'   => ['required','date_format:H:i','after:from_time'],
        'is_active' => ['sometimes','boolean'],
    ]);

    $isActive = $data['is_active'] ?? true;


    TherapistChatAvailability::where('therapist_id', $t->id)->delete();

 
    foreach (array_unique($data['days']) as $day) {
        TherapistChatAvailability::create([
            'therapist_id' => $t->id,
            'day_of_week'  => (int) $day,
            'from_time'    => $data['from_time'],
            'to_time'      => $data['to_time'],
            'is_active'    => $isActive,
        ]);
    }

    $items = TherapistChatAvailability::where('therapist_id', $t->id)
        ->orderBy('day_of_week')
        ->get();

    return response()->json([
        'message' => 'Chat availability replaced',
        'data'    => TherapistChatAvailabilityResource::collection($items),
    ]);
}

}
