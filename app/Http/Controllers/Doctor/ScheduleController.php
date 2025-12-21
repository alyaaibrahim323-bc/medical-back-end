<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Doctor\StoreScheduleRequest;
use App\Models\Therapist;
use App\Models\TherapistSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    protected function therapistOfDoctor()
    {
        $user = Auth::user();
        return Therapist::where('user_id', $user->id)->firstOrFail();
    }

    public function index()
    {
        $t = $this->therapistOfDoctor();
        return response()->json(['data'=>$t->schedules()->orderBy('weekday')->get()]);
    }

    public function store(Request $request)
{
    $data = $request->validate([
        'weekday'      => ['required','array','min:1'],
        'weekday.*'    => ['string','in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],
        'start_time'   => ['required','date_format:H:i'],
        'end_time'     => ['required','date_format:H:i','after:start_time'],
        'slot_minutes' => ['required','integer','min:15','max:240'],
        'is_active'    => ['nullable','boolean'],
    ]);

    $therapistId = $request->user()->therapist->id;

    // 1) تطبيع وتحويل الأسماء لأرقام
    $nameToNum = [
        'sunday'=>0,'monday'=>1,'tuesday'=>2,'wednesday'=>3,
        'thursday'=>4,'friday'=>5,'saturday'=>6,
    ];
    $weeknames = array_values(array_unique(array_map(fn($x)=>strtolower(trim($x)), $data['weekday'])));
    $weeknums  = array_map(fn($name)=>$nameToNum[$name], $weeknames);

    // 2) الأيام الموجودة بالفعل
    $existing = \App\Models\TherapistSchedule::query()
        ->where('therapist_id', $therapistId)
        ->whereIn('weekday', $weeknums)
        ->pluck('weekday')
        ->all();

    $now = now();

    // ✅ UPDATE الأيام الموجودة
    if (!empty($existing)) {
        \App\Models\TherapistSchedule::where('therapist_id', $therapistId)
            ->whereIn('weekday', $existing)
            ->update([
                'start_time'   => $data['start_time'],
                'end_time'     => $data['end_time'],
                'slot_minutes' => $data['slot_minutes'],
                'is_active'    => $data['is_active'] ?? true,
                'updated_at'   => $now,
            ]);
    }

    // 3) اللى هننشئه فعلاً (الجديد بس)
    $toCreateNums = array_values(array_diff($weeknums, $existing));

    // 4) تجهيز البلود لتحزين جماعي
    $payload = array_map(function($w) use ($therapistId, $data, $now){
        return [
            'therapist_id' => $therapistId,
            'weekday'      => $w,
            'start_time'   => $data['start_time'],
            'end_time'     => $data['end_time'],
            'slot_minutes' => $data['slot_minutes'],
            'is_active'    => $data['is_active'] ?? true,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
    }, $toCreateNums);

    if (!empty($payload)) {
        \App\Models\TherapistSchedule::insert($payload);
    }

    // 5) رجّع أسماء الأيام (مش الأرقام) فى created / skipped (زي ما هي)
    $numToName = array_flip($nameToNum);
    $createdNames = array_map(fn($n)=>$numToName[$n], $toCreateNums);
    $skippedNames = array_map(fn($n)=>$numToName[$n], $existing);

    return response()->json([
        'message'      => 'Schedules created successfully',
        'created'      => $createdNames,
        'skipped_days' => $skippedNames, // نفس الاسم، بس معناها الآن "updated"
    ]);
}




    public function update(StoreScheduleRequest $request, $id)
    {
        $t = $this->therapistOfDoctor();
        $s = TherapistSchedule::where('therapist_id',$t->id)->findOrFail($id);
        $s->update($request->validated());
        return response()->json(['data'=>$s]);
    }

    public function destroy($id)
    {
        $t = $this->therapistOfDoctor();
        TherapistSchedule::where('therapist_id',$t->id)->findOrFail($id)->delete();
        return response()->json(['message'=>'Deleted']);
    }

    public function replace(Request $request)
{
    $data = $request->validate([
        'weekday'      => ['required','array','min:1'],
        'weekday.*'    => ['string','in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],
        'start_time'   => ['required','date_format:H:i'],
        'end_time'     => ['required','date_format:H:i','after:start_time'],
        'slot_minutes' => ['required','integer','min:15','max:240'],
        'is_active'    => ['nullable','boolean'],
    ]);

    $therapistId = $request->user()->therapist->id;

    // 1) تطبيع وتحويل الأسماء لأرقام
    $nameToNum = [
        'sunday'=>0,'monday'=>1,'tuesday'=>2,'wednesday'=>3,
        'thursday'=>4,'friday'=>5,'saturday'=>6,
    ];

    $weeknames = array_values(array_unique(array_map(fn($x)=>strtolower(trim($x)), $data['weekday'])));
    $weeknums  = array_map(fn($name)=>$nameToNum[$name], $weeknames);

    // 2) DELETE ALL old schedules for this therapist
    TherapistSchedule::where('therapist_id', $therapistId)->delete();

    // 3) Bulk insert new
    $now = now();
    $payload = array_map(function($w) use ($therapistId, $data, $now) {
        return [
            'therapist_id' => $therapistId,
            'weekday'      => $w,
            'start_time'   => $data['start_time'],
            'end_time'     => $data['end_time'],
            'slot_minutes' => $data['slot_minutes'],
            'is_active'    => $data['is_active'] ?? true,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
    }, $weeknums);

    TherapistSchedule::insert($payload);

    return response()->json([
        'message' => 'Schedules replaced successfully',
        'created' => $weeknames, // نفس اللي اتبعت
        'data'    => TherapistSchedule::where('therapist_id', $therapistId)->orderBy('weekday')->get(),
    ]);
}

}
