<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesContentInput;
use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;

class WeddingHeroContentRequest extends FormRequest
{
    use NormalizesContentInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $plain = new PlainText;

        return ['eyebrow' => ['nullable', 'string', 'max:150', $plain], 'headline' => ['nullable', 'string', 'max:255', $plain], 'subheadline' => ['nullable', 'string', 'max:1000', $plain], 'mediaUrl' => ['nullable', 'url', 'max:2048'], 'mediaAltText' => ['nullable', 'string', 'max:500', $plain], 'isPublished' => ['required', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeContentFields(['eyebrow', 'headline', 'subheadline', 'mediaUrl', 'mediaAltText']);
    }
}
