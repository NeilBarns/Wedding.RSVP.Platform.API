<?php

namespace App\Http\Requests;

use App\Models\Guest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SubmitPublicRsvpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guests' => ['required', 'array', 'min:1'],
            'guests.*' => [
                'required',
                'array:id,attendanceStatus,dietaryRequirements,accessibilityRequirements',
            ],
            'guests.*.id' => ['required', 'integer', 'distinct:strict'],
            'guests.*.attendanceStatus' => [
                'required',
                Rule::in([
                    Guest::ATTENDANCE_ATTENDING,
                    Guest::ATTENDANCE_DECLINED,
                ]),
            ],
            'guests.*.dietaryRequirements' => ['nullable', 'string', 'max:2000'],
            'guests.*.accessibilityRequirements' => ['nullable', 'string', 'max:2000'],
            'contactNumber' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:254'],
            'message' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach (['contactNumber', 'email', 'message'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = $this->normalize($data[$field]);
            }
        }

        if (is_string($data['email'] ?? null)) {
            $data['email'] = Str::lower($data['email']);
        }

        if (is_array($data['guests'] ?? null)) {
            foreach ($data['guests'] as $index => $guest) {
                if (! is_array($guest)) {
                    continue;
                }

                foreach (['dietaryRequirements', 'accessibilityRequirements'] as $field) {
                    if (array_key_exists($field, $guest) && is_string($guest[$field])) {
                        $data['guests'][$index][$field] = $this->normalize($guest[$field]);
                    }
                }
            }
        }

        $this->replace($data);
    }

    private function normalize(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
