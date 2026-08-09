<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesContentInput;
use App\Models\Wedding;
use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        $weddingId = Wedding::currentSingleWedding()?->id ?? -1;

        return ['eyebrow' => ['nullable', 'string', 'max:150', $plain], 'headline' => ['nullable', 'string', 'max:255', $plain], 'subheadline' => ['nullable', 'string', 'max:1000', $plain], 'mediaUrl' => ['nullable', 'url', 'max:2048'], 'heroMediaId' => ['nullable', 'integer', Rule::exists('wedding_media', 'id')->where('wedding_id', $weddingId)], 'mediaAltText' => ['nullable', 'string', 'max:500', $plain], 'isPublished' => ['required', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeContentFields(['eyebrow', 'headline', 'subheadline', 'mediaUrl', 'mediaAltText']);
    }
}
