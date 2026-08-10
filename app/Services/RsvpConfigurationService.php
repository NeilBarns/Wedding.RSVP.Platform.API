<?php

namespace App\Services;

use App\Enums\RsvpQuestionKey;
use App\Enums\RsvpQuestionScope;
use App\Models\Wedding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RsvpConfigurationService
{
    private const DEFINITIONS = [
        'dietaryRequirements' => ['scope' => 'guest', 'enabled' => true, 'label' => 'Dietary requirements', 'helperText' => null, 'sortOrder' => 10, 'type' => 'text'],
        'accessibilityNeeds' => ['scope' => 'guest', 'enabled' => true, 'label' => 'Accessibility needs', 'helperText' => null, 'sortOrder' => 20, 'type' => 'text'],
        'mealChoice' => ['scope' => 'guest', 'enabled' => false, 'label' => 'Meal choice', 'helperText' => null, 'sortOrder' => 30, 'type' => 'singleChoice'],
        'responsePhone' => ['scope' => 'household', 'enabled' => true, 'label' => 'Contact number', 'helperText' => null, 'sortOrder' => 10, 'type' => 'text'],
        'responseEmail' => ['scope' => 'household', 'enabled' => true, 'label' => 'Email address', 'helperText' => null, 'sortOrder' => 20, 'type' => 'text'],
        'messageToCouple' => ['scope' => 'household', 'enabled' => true, 'label' => 'Message to the couple', 'helperText' => null, 'sortOrder' => 30, 'type' => 'textarea'],
    ];

    public function questions(Wedding $wedding, bool $includeAttendance = true, bool $publicSafe = false): Collection
    {
        $wedding->loadMissing(['rsvpQuestions', 'rsvpQuestionOptions']);
        $persisted = $wedding->rsvpQuestions->keyBy(fn ($question) => $question->key->value);
        $options = $wedding->rsvpQuestionOptions
            ->where('question_key', RsvpQuestionKey::MealChoice)
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']]);

        $questions = collect(self::DEFINITIONS)->map(function (array $definition, string $key) use ($persisted, $options, $publicSafe) {
            $question = $persisted->get($key);
            $resolved = [
                'key' => $key,
                'scope' => $definition['scope'],
                'enabled' => $question?->enabled ?? $definition['enabled'],
                'required' => $question?->required ?? false,
                'label' => $question?->label ?? $definition['label'],
                'helperText' => $question?->helper_text ?? $definition['helperText'],
                'sortOrder' => $question?->sort_order ?? $definition['sortOrder'],
                'system' => false,
                'type' => $definition['type'],
            ];

            if ($key === RsvpQuestionKey::MealChoice->value) {
                $resolved['options'] = $options
                    ->when($publicSafe, fn (Collection $items) => $items->where('enabled', true))
                    ->values()
                    ->map(fn ($option) => $publicSafe ? [
                        'label' => $option->label,
                        'value' => $option->value,
                        'sortOrder' => $option->sort_order,
                    ] : [
                        'id' => $option->id,
                        'label' => $option->label,
                        'value' => $option->value,
                        'sortOrder' => $option->sort_order,
                        'enabled' => $option->enabled,
                    ])->all();
            }

            return $resolved;
        })->values();

        if ($includeAttendance) {
            $questions->prepend([
                'key' => 'attendance',
                'scope' => RsvpQuestionScope::Guest->value,
                'enabled' => true,
                'required' => true,
                'label' => 'Attendance',
                'helperText' => null,
                'sortOrder' => 0,
                'system' => true,
                'type' => 'singleChoice',
            ]);
        }

        return $questions->sortBy([
            ['scope', 'asc'],
            ['sortOrder', 'asc'],
            ['key', 'asc'],
        ])->values();
    }

    public function grouped(Wedding $wedding): array
    {
        $questions = $this->questions($wedding, true, true);

        return [
            'guestQuestions' => $questions->where('scope', RsvpQuestionScope::Guest->value)->values()->all(),
            'householdQuestions' => $questions->where('scope', RsvpQuestionScope::Household->value)->values()->all(),
        ];
    }

    public function byKey(Wedding $wedding): Collection
    {
        return $this->questions($wedding, false)->keyBy('key');
    }

    public function replace(Wedding $wedding, array $questions): void
    {
        DB::transaction(function () use ($wedding, $questions) {
            foreach ($questions as $question) {
                $key = RsvpQuestionKey::from($question['key']);
                $wedding->rsvpQuestions()->updateOrCreate(
                    ['key' => $key->value],
                    [
                        'scope' => $key->scope()->value,
                        'enabled' => $question['enabled'],
                        'required' => $question['required'],
                        'label' => $question['label'],
                        'helper_text' => $question['helperText'],
                        'sort_order' => $question['sortOrder'],
                    ],
                );
            }

            $meal = collect($questions)->firstWhere('key', RsvpQuestionKey::MealChoice->value);
            if ($meal !== null) {
                $submittedValues = collect($meal['options'])->pluck('value');
                $wedding->rsvpQuestionOptions()
                    ->where('question_key', RsvpQuestionKey::MealChoice->value)
                    ->whereNotIn('value', $submittedValues)
                    ->update(['enabled' => false]);

                foreach ($meal['options'] as $option) {
                    $wedding->rsvpQuestionOptions()->updateOrCreate(
                        [
                            'question_key' => RsvpQuestionKey::MealChoice->value,
                            'value' => $option['value'],
                        ],
                        [
                            'label' => $option['label'],
                            'sort_order' => $option['sortOrder'],
                            'enabled' => $option['enabled'],
                        ],
                    );
                }
            }
        });

        $wedding->unsetRelation('rsvpQuestions');
        $wedding->unsetRelation('rsvpQuestionOptions');
    }

    public static function supportedKeys(): array
    {
        return array_column(RsvpQuestionKey::cases(), 'value');
    }
}
