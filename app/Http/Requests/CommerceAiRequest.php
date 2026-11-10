<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommerceAiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message'         => ['required', 'string', 'max:5000'],
            'conversation_id' => ['nullable', 'string', 'max:64'],
            'attachments'     => ['nullable', 'array', 'max:5'],
            'attachments.*'   => ['file', 'mimes:jpg,jpeg,png,webp', 'max:' . (int) config('ai_commerce.attachments.max_size_kb', 5120)],
        ];
    }
}
