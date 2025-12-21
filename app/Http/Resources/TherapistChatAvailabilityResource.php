<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TherapistChatAvailabilityResource extends JsonResource
{
    private static array $days = [
        0 => ['en'=>'Sunday',    'ar'=>'الأحد'],
        1 => ['en'=>'Monday',    'ar'=>'الإثنين'],
        2 => ['en'=>'Tuesday',   'ar'=>'الثلاثاء'],
        3 => ['en'=>'Wednesday', 'ar'=>'الأربعاء'],
        4 => ['en'=>'Thursday',  'ar'=>'الخميس'],
        5 => ['en'=>'Friday',    'ar'=>'الجمعة'],
        6 => ['en'=>'Saturday',  'ar'=>'السبت'],
    ];

    public function toArray($request)
    {
        $d = (int) $this->day_of_week;
        $names = self::$days[$d] ?? ['en'=>'', 'ar'=>''];

        // label حسب Accept-Language (اختياري)
        $lang = strtolower(substr((string)$request->header('Accept-Language','en'), 0, 2));
        $label = $lang === 'ar' ? ($names['ar'] ?? '') : ($names['en'] ?? '');

        return [
            'day_of_week' => $d,
            'day_name'    => $names,      // ✅ {en, ar}
            'day_label'   => $label,      // ✅ حسب اللغة
            'from'        => substr((string)$this->from_time, 0, 5),
            'to'          => substr((string)$this->to_time, 0, 5),
            'is_active'   => (bool) $this->is_active,
        ];
    }
}
