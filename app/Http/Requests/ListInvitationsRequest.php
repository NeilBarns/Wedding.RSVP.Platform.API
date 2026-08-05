<?php

namespace App\Http\Requests;

use App\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListInvitationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(Invitation::STATUSES)],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sortBy' => ['nullable', Rule::in(['displayName', 'status', 'createdAt', 'updatedAt', 'submittedAt'])],
            'sortDirection' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('search'))) {
            $search = trim($this->input('search'));
            $this->merge(['search' => $search === '' ? null : $search]);
        }
    }
}
