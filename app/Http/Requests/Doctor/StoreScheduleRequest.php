<?php

namespace App\Http\Requests\Doctor;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'weekday'       => ['required','integer','between:0,6'],
            'start_time'    => ['required','date_format:H:i'],
            'end_time'      => ['required','date_format:H:i','after:start_time'],
            'slot_minutes'  => ['nullable','integer','min:15','max:240'],
            'is_active'     => ['boolean'],
        ];
    }
}
