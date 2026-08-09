<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesContentInput;
use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;

class WeddingStoryEntryRequest extends FormRequest
{
    use NormalizesContentInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $plain = new PlainText;

        return ['title' => ['nullable', 'string', 'max:255', $plain], 'body' => ['required', 'string', 'max:10000', $plain], 'imageUrl' => ['nullable', 'url', 'max:2048'], 'imageAltText' => ['nullable', 'string', 'max:500', $plain], 'eventDate' => ['nullable', 'date_format:Y-m-d'], 'sortOrder' => ['required', 'integer', 'min:0'], 'isPublished' => ['required', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeContentFields(['title', 'imageUrl', 'imageAltText'], ['body']);
    }
}
