<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesContentInput;
use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;

class WeddingFaqEntryRequest extends FormRequest
{
    use NormalizesContentInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $plain = new PlainText;

        return ['question' => ['required', 'string', 'max:500', $plain], 'answer' => ['required', 'string', 'max:10000', $plain], 'sortOrder' => ['required', 'integer', 'min:0'], 'isPublished' => ['required', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeContentFields([], ['question', 'answer']);
    }
}
