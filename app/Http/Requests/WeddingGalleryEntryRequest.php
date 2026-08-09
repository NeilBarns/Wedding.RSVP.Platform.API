<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesContentInput;
use App\Models\Wedding;
use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        $weddingId = Wedding::currentSingleWedding()?->id ?? -1;

        return ['imageUrl' => ['nullable', 'required_without:mediaId', 'url', 'max:2048'], 'mediaId' => ['nullable', 'required_without:imageUrl', 'integer', Rule::exists('wedding_media', 'id')->where('wedding_id', $weddingId)], 'altText' => ['nullable', 'string', 'max:500', $plain], 'caption' => ['nullable', 'string', 'max:2000', $plain], 'sortOrder' => ['required', 'integer', 'min:0'], 'isPublished' => ['required', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeContentFields(['altText', 'caption'], ['imageUrl']);
    }
}
