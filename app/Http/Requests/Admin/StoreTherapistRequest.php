<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTherapistRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'user_id'      => ['required','exists:users,id'],
            'specialty'    => ['nullable'], // string أو object {en,ar}
            'bio'          => ['nullable'],
            'price_cents'  => ['required','integer','min:0'],
            'currency'     => ['required','string','size:3'],
            'is_active'    => ['boolean'],
        ];
    }
}
