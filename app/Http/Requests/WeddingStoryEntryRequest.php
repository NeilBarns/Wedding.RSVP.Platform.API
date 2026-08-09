<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesContentInput;
use App\Models\Wedding;
use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        $weddingId = Wedding::currentSingleWedding()?->id ?? -1;

        return ['title' => ['nullable', 'string', 'max:255', $plain], 'body' => ['required', 'string', 'max:10000', $plain], 'imageUrl' => ['nullable', 'url', 'max:2048'], 'imageMediaId' => ['nullable', 'integer', Rule::exists('wedding_media', 'id')->where('wedding_id', $weddingId)], 'imageAltText' => ['nullable', 'string', 'max:500', $plain], 'eventDate' => ['nullable', 'date_format:Y-m-d'], 'sortOrder' => ['required', 'integer', 'min:0'], 'isPublished' => ['required', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeContentFields(['title', 'imageUrl', 'imageAltText'], ['body']);
    }
}
