<?php

namespace App\Http\Requests;

use App\Enums\RsvpQuestionKey;
use App\Services\RsvpConfigurationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRsvpConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'questions' => ['required', 'array', 'size:'.count(RsvpQuestionKey::cases())],
            'questions.*' => ['required', 'array:key,enabled,required,label,helperText,sortOrder'],
            'questions.*.key' => ['required', 'string', 'distinct:strict', Rule::enum(RsvpQuestionKey::class)],
            'questions.*.enabled' => ['required', 'boolean'],
            'questions.*.required' => ['required', 'boolean'],
            'questions.*.label' => ['required', 'string', 'max:150'],
            'questions.*.helperText' => ['nullable', 'string', 'max:500'],
            'questions.*.sortOrder' => ['required', 'integer', 'min:0', 'max:10000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $questions = collect($this->input('questions', []));
            $submittedKeys = $questions->pluck('key')->filter(fn ($key) => is_string($key))->sort()->values()->all();
            $supportedKeys = collect(RsvpConfigurationService::supportedKeys())->sort()->values()->all();

            if ($submittedKeys !== $supportedKeys) {
                $validator->errors()->add('questions', 'Every supported configurable RSVP question must be provided exactly once.');
            }

            foreach ($questions as $index => $question) {
                if (is_array($question) && ($question['enabled'] ?? null) === false && ($question['required'] ?? null) === true) {
                    $validator->errors()->add("questions.{$index}.required", 'A disabled RSVP question cannot be required.');
                }
            }

            foreach (RsvpQuestionKey::cases() as $key) {
                $orders = $questions
                    ->filter(fn ($question) => is_array($question)
                        && in_array($question['key'] ?? null, array_map(
                            fn (RsvpQuestionKey $candidate) => $candidate->value,
                            array_filter(RsvpQuestionKey::cases(), fn (RsvpQuestionKey $candidate) => $candidate->scope() === $key->scope()),
                        ), true))
                    ->pluck('sortOrder');

                if ($orders->count() !== $orders->uniqueStrict()->count()) {
                    $validator->errors()->add('questions', 'Sort orders must be unique within each RSVP question scope.');
                    break;
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $questions = $this->input('questions');

        if (! is_array($questions)) {
            return;
        }

        foreach ($questions as $index => $question) {
            if (! is_array($question)) {
                continue;
            }

            foreach (['label', 'helperText'] as $field) {
                if (array_key_exists($field, $question) && is_string($question[$field])) {
                    $value = trim($question[$field]);
                    $questions[$index][$field] = $field === 'helperText' && $value === '' ? null : $value;
                }
            }
        }

        $this->merge(['questions' => $questions]);
    }
}
