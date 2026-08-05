<?php

namespace App\Http\Requests;

use App\Models\Guest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'guests' => ['required', 'array', 'min:1', 'max:20'],
            'guests.*.fullName' => ['required', 'string', 'max:150'],
            'guests.*.guestType' => ['required', Rule::in(Guest::TYPES)],
            'guests.*.sortOrder' => ['required', 'integer', 'min:0', 'max:10000'],
            'guests.*.dietaryRequirements' => ['nullable', 'string', 'max:2000'],
            'guests.*.accessibilityRequirements' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->normalizeProfile($this->all());

        if (isset($data['guests']) && is_array($data['guests'])) {
            $data['guests'] = array_map(fn ($guest) => is_array($guest)
                ? $this->normalizeGuest($guest)
                : $guest, $data['guests']);
        }

        $this->replace($data);
    }

    protected function profileRules(): array
    {
        return [
            'displayName' => ['required', 'string', 'max:150'],
            'contactPersonName' => ['nullable', 'string', 'max:150'],
            'contactNumber' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:254'],
            'internalNotes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    protected function normalizeProfile(array $data): array
    {
        foreach (['displayName', 'contactPersonName', 'contactNumber', 'email', 'internalNotes'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = $this->nullableTrimmed($data[$field]);
            }
        }

        if (isset($data['email'])) {
            $data['email'] = strtolower($data['email']);
        }

        return $data;
    }

    protected function normalizeGuest(array $guest): array
    {
        foreach (['fullName', 'dietaryRequirements', 'accessibilityRequirements'] as $field) {
            if (array_key_exists($field, $guest) && is_string($guest[$field])) {
                $guest[$field] = $this->nullableTrimmed($guest[$field]);
            }
        }

        return $guest;
    }

    private function nullableTrimmed(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
