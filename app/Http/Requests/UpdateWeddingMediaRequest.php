<?php

namespace App\Http\Requests;

use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWeddingMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['altText' => ['present', 'nullable', 'string', 'max:500', new PlainText]];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->altText)) {
            $this->merge(['altText' => trim($this->altText) ?: null]);
        }
    }
}
