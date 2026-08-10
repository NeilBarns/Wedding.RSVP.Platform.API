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
                'array:id,attendanceStatus,dietaryRequirements,accessibilityRequirements,mealChoice',
            ],
            'guests.*.id' => ['required', 'integer', 'distinct:strict'],
            'guests.*.attendanceStatus' => [
                'required',
                Rule::in([
                    Guest::ATTENDANCE_ATTENDING,
                    Guest::ATTENDANCE_DECLINED,
                ]),
            ],
            // Configuration-aware format and required rules are applied after the invitation is resolved.
            'guests.*.dietaryRequirements' => ['nullable'],
            'guests.*.accessibilityRequirements' => ['nullable'],
            'guests.*.mealChoice' => ['nullable'],
            'contactNumber' => ['nullable'],
            'email' => ['nullable'],
            'message' => ['nullable'],
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

                if (array_key_exists('mealChoice', $guest) && is_string($guest['mealChoice'])) {
                    $data['guests'][$index]['mealChoice'] = $this->normalize($guest['mealChoice']);
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
