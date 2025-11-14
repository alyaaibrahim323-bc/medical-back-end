<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTherapistRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'specialty'    => ['sometimes'],
            'bio'          => ['sometimes'],
            'price_cents'  => ['sometimes','integer','min:0'],
            'currency'     => ['sometimes','string','size:3'],
            'is_active'    => ['sometimes','boolean'],
        ];
    }
}
