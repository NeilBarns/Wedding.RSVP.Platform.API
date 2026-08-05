<?php

namespace App\Http\Requests;

use App\Models\Guest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:150'],
            'guestType' => ['required', Rule::in(Guest::TYPES)],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:10000'],
            'dietaryRequirements' => ['nullable', 'string', 'max:2000'],
            'accessibilityRequirements' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (['fullName', 'dietaryRequirements', 'accessibilityRequirements'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $value = trim($data[$field]);
                $data[$field] = $value === '' ? null : $value;
            }
        }

        $this->replace($data);
    }
}
