<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReadChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message_id' => 'required|exists:chat_messages,id',
        ];
    }
}
