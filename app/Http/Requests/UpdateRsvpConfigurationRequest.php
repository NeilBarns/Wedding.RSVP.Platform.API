<?php

namespace App\Http\Requests;

use App\Enums\RsvpQuestionKey;
use App\Models\Wedding;
use App\Services\RsvpConfigurationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
            'questions.*' => ['required', 'array:key,enabled,required,label,helperText,sortOrder,options'],
            'questions.*.key' => ['required', 'string', 'distinct:strict', Rule::enum(RsvpQuestionKey::class)],
            'questions.*.enabled' => ['required', 'boolean'],
            'questions.*.required' => ['required', 'boolean'],
            'questions.*.label' => ['required', 'string', 'max:150'],
            'questions.*.helperText' => ['nullable', 'string', 'max:500'],
            'questions.*.sortOrder' => ['required', 'integer', 'min:0', 'max:10000'],
            'questions.*.options' => ['sometimes', 'array'],
            'questions.*.options.*' => ['required', 'array:id,label,value,sortOrder,enabled'],
            'questions.*.options.*.id' => ['sometimes', 'nullable', 'integer', 'distinct:strict'],
            'questions.*.options.*.label' => ['required', 'string', 'max:150'],
            'questions.*.options.*.value' => ['required', 'string', 'max:80', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', 'distinct:strict'],
            'questions.*.options.*.sortOrder' => ['required', 'integer', 'min:0', 'max:10000'],
            'questions.*.options.*.enabled' => ['required', 'boolean'],
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

                if (! is_array($question)) {
                    continue;
                }

                if (($question['key'] ?? null) !== RsvpQuestionKey::MealChoice->value && array_key_exists('options', $question)) {
                    $validator->errors()->add("questions.{$index}.options", 'Only Meal Choice may define RSVP options.');
                }
            }

            $mealIndex = $questions->search(fn ($question) => is_array($question) && ($question['key'] ?? null) === RsvpQuestionKey::MealChoice->value);
            if ($mealIndex !== false) {
                $meal = $questions[$mealIndex];
                $options = collect($meal['options'] ?? []);

                if (! array_key_exists('options', $meal)) {
                    $validator->errors()->add("questions.{$mealIndex}.options", 'Meal Choice options must be provided.');
                }

                if (($meal['enabled'] ?? false) === true && $options->where('enabled', true)->count() < 2) {
                    $validator->errors()->add("questions.{$mealIndex}.options", 'Enabled Meal Choice requires at least two enabled options.');
                }

                $orders = $options->pluck('sortOrder');
                if ($orders->count() !== $orders->uniqueStrict()->count()) {
                    $validator->errors()->add("questions.{$mealIndex}.options", 'Meal Choice option sort orders must be unique.');
                }

                $wedding = Wedding::currentSingleWedding();
                if ($wedding !== null) {
                    $existing = $wedding->rsvpQuestionOptions()->get()->keyBy('id');
                    foreach ($options as $optionIndex => $option) {
                        if (! is_array($option) || empty($option['id'])) {
                            continue;
                        }

                        $persisted = $existing->get((int) $option['id']);
                        if ($persisted === null || $persisted->question_key !== RsvpQuestionKey::MealChoice || $persisted->value !== ($option['value'] ?? null)) {
                            $validator->errors()->add("questions.{$mealIndex}.options.{$optionIndex}.value", 'Existing option values are immutable.');
                        }
                    }
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

            if (is_array($question['options'] ?? null)) {
                foreach ($question['options'] as $optionIndex => $option) {
                    if (! is_array($option)) {
                        continue;
                    }

                    if (isset($option['label']) && is_string($option['label'])) {
                        $questions[$index]['options'][$optionIndex]['label'] = trim($option['label']);
                    }

                    if (isset($option['value']) && is_string($option['value'])) {
                        $questions[$index]['options'][$optionIndex]['value'] = Str::lower(trim($option['value']));
                    }
                }
            }
        }

        $this->merge(['questions' => $questions]);
    }
}
