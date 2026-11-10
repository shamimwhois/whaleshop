<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaginateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitize sorting input before it is used in orderBy().
     *
     * Column names cannot be bound as query parameters, so any user supplied
     * value must be restricted to a safe identifier, and the direction to a
     * whitelisted keyword.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('order_column')) {
            $column = $this->input('order_column');

            $this->merge([
                'order_column' => is_string($column) && preg_match('/^[A-Za-z0-9_]+$/', $column)
                    ? $column
                    : 'id',
            ]);
        }

        if ($this->has('order_type')) {
            $type = $this->input('order_type');

            $this->merge([
                'order_type' => is_string($type) && in_array(strtolower($type), ['asc', 'desc'], true)
                    ? strtolower($type)
                    : 'desc',
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'per_page' => ['numeric', 'min:1', 'max:1000'],
            'order_column' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_]+$/'],
            'order_type' => ['nullable', 'in:asc,desc'],
        ];
    }
}
