<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Resources\TherapistChatAvailabilityResource;
use App\Models\TherapistChatAvailability;
use Illuminate\Http\Request;

class ChatAvailabilityController extends Controller
{
    // GET /doctor/me/chat-availability
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

    /**
     * POST /doctor/me/chat-availability
     * upsert لأيام متعددة (مودال الداشبورد)
     */
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

    // PATCH /doctor/me/chat-availability/{day}
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

    // DELETE /doctor/me/chat-availability/{day}
    public function destroy(Request $r, int $day)
    {
        $t = $r->user()->therapist()->firstOrFail();
        abort_if($day < 0 || $day > 6, 404);

        TherapistChatAvailability::where('therapist_id', $t->id)
            ->where('day_of_week', $day)
            ->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /**
     * ✅ BULK REPLACE
     * PUT /doctor/me/chat-availability
     * Body:
     * {
     *   "items": [
     *     {"day_of_week":0,"from_time":"12:00","to_time":"16:00","is_active":true},
     *     {"day_of_week":1,"from_time":"18:00","to_time":"20:00"}
     *   ]
     * }
     * - يمسح القديم بالكامل ويكتب الجديد
     */
    public function replace(Request $r)
    {
        $t = $r->user()->therapist()->firstOrFail();

        $data = $r->validate([
            'items'              => ['required','array'],
            'items.*.day_of_week'=> ['required','integer','min:0','max:6'],
            'items.*.from_time'  => ['required','date_format:H:i'],
            'items.*.to_time'    => ['required','date_format:H:i'],
            'items.*.is_active'  => ['sometimes','boolean'],
        ]);

        // validate from < to لكل عنصر
        foreach ($data['items'] as $it) {
            if ($it['to_time'] <= $it['from_time']) {
                return response()->json([
                    'message' => 'to_time must be after from_time',
                ], 422);
            }
        }

        // replace
        TherapistChatAvailability::where('therapist_id', $t->id)->delete();

        foreach ($data['items'] as $it) {
            TherapistChatAvailability::create([
                'therapist_id' => $t->id,
                'day_of_week'  => (int)$it['day_of_week'],
                'from_time'    => $it['from_time'],
                'to_time'      => $it['to_time'],
                'is_active'    => $it['is_active'] ?? true,
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
