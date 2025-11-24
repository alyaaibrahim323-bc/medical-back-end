<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'          => 'nullable|in:text,image,audio,file,system',
            'body'          => 'nullable|string',
            'file'          => 'nullable|file|max:10240', // 10 MB
            'duration_ms'   => 'nullable|integer|min:1',
            'replied_to_id' => 'nullable|exists:chat_messages,id',
        ];
    }
}
