<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'therapist_id' => 'required|exists:therapists,id',
        ];
    }
}
