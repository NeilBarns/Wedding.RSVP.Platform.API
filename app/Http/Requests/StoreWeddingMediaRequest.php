<?php

namespace App\Http\Requests;

use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreWeddingMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(config('filesystems.wedding_media_max_kilobytes')), Rule::dimensions()->minWidth(config('filesystems.wedding_media_min_width'))->minHeight(config('filesystems.wedding_media_min_height'))],
            'altText' => ['nullable', 'string', 'max:500', new PlainText],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->altText)) {
            $this->merge(['altText' => trim($this->altText) ?: null]);
        }
    }
}
