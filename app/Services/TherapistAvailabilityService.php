<?php

namespace App\Services;

use App\Models\TherapistSchedule;
use App\Models\TherapistTimeoff;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\TherapySession;


class TherapistAvailabilityService
{

    public function slotsForRange(int $therapistId, Carbon $from, Carbon $to, int $defaultSlot = 60): array
    {
        $schedules = TherapistSchedule::where('therapist_id', $therapistId)
            ->where('is_active', true)->get();
        $timeoffs  = TherapistTimeoff::where('therapist_id', $therapistId)
            ->whereBetween('off_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('off_date')->map(fn($d)=>Carbon::parse($d)->toDateString())->all();

        $days = [];
        foreach (CarbonPeriod::create($from, '1 day', $to) as $day) {
            $date = $day->toDateString();
            if (in_array($date, $timeoffs, true)) { $days[$date] = []; continue; }

            $weekday = $day->dayOfWeek;
            $shifts  = $schedules->where('weekday', $weekday);

            $slots = [];
            foreach ($shifts as $s) {
                $start = Carbon::parse($date.' '.$s->start_time);
                $end   = Carbon::parse($date.' '.$s->end_time);
                $step  = $s->slot_minutes ?: $defaultSlot;

                for ($t = $start->copy(); $t->lt($end); $t->addMinutes($step)) {
                    $slotEnd = $t->copy()->addMinutes($step);
                    if ($slotEnd->gt($end)) break;
                   if ($this->isSlotFree($therapistId, $t, $slotEnd)) {
                    $slots[] = [
                        'start' => $t->toIso8601String(),
                        'end'   => $slotEnd->toIso8601String(),
                        'duration_min' => $step,
                    ];
                }

                }
            }
            $days[$date] = array_values($slots);
        }

        return $days;
    }
    public function isSlotFree(int $therapistId, Carbon $start, Carbon $end): bool
    {
        return ! TherapySession::where('therapist_id', $therapistId)
            ->whereNotIn('status', [
                TherapySession::STATUS_CANCELLED,
                TherapySession::STATUS_NO_SHOW,
            ])

            ->where('scheduled_at', '<', $end)
            ->whereRaw(
                'DATE_ADD(scheduled_at, INTERVAL duration_min MINUTE) > ?',
                [$start]
            )
            ->exists();
    }
}
