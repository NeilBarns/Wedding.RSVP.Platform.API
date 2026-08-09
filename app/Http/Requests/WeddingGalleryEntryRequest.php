<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesContentInput;
use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;

class WeddingGalleryEntryRequest extends FormRequest
{
    use NormalizesContentInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $plain = new PlainText;

        return ['imageUrl' => ['required', 'url', 'max:2048'], 'altText' => ['nullable', 'string', 'max:500', $plain], 'caption' => ['nullable', 'string', 'max:2000', $plain], 'sortOrder' => ['required', 'integer', 'min:0'], 'isPublished' => ['required', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeContentFields(['altText', 'caption'], ['imageUrl']);
    }
}
