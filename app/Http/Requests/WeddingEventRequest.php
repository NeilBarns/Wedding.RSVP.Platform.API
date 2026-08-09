<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesContentInput;
use App\Models\WeddingEvent;
use App\Rules\PlainText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WeddingEventRequest extends FormRequest
{
    use NormalizesContentInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $plain = new PlainText;

        return ['title' => ['required', 'string', 'max:255', $plain], 'eventType' => ['required', Rule::in(WeddingEvent::TYPES)], 'eventDate' => ['required', 'date_format:Y-m-d'], 'startTime' => ['nullable', 'date_format:H:i'], 'endTime' => ['nullable', 'date_format:H:i', 'after:startTime'], 'venueName' => ['nullable', 'string', 'max:255', $plain], 'addressLine' => ['nullable', 'string', 'max:1000', $plain], 'mapUrl' => ['nullable', 'url', 'max:2048'], 'description' => ['nullable', 'string', 'max:5000', $plain], 'dressCodeOverride' => ['nullable', 'string', 'max:1000', $plain], 'sortOrder' => ['required', 'integer', 'min:0'], 'isPublished' => ['required', 'boolean']];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeContentFields(['startTime', 'endTime', 'venueName', 'addressLine', 'mapUrl', 'description', 'dressCodeOverride'], ['title']);
    }
}
