<?php

namespace App\Http\Requests\Doctor;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeoffRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'off_date' => ['required','date','after_or_equal:today'],
            'reason'   => ['nullable','string','max:120'],
        ];
    }
}
