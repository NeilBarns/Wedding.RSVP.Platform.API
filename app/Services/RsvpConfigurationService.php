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
        'dietaryRequirements' => ['scope' => 'guest', 'label' => 'Dietary requirements', 'helperText' => null, 'sortOrder' => 10],
        'accessibilityNeeds' => ['scope' => 'guest', 'label' => 'Accessibility needs', 'helperText' => null, 'sortOrder' => 20],
        'responsePhone' => ['scope' => 'household', 'label' => 'Contact number', 'helperText' => null, 'sortOrder' => 10],
        'responseEmail' => ['scope' => 'household', 'label' => 'Email address', 'helperText' => null, 'sortOrder' => 20],
        'messageToCouple' => ['scope' => 'household', 'label' => 'Message to the couple', 'helperText' => null, 'sortOrder' => 30],
    ];

    public function questions(Wedding $wedding, bool $includeAttendance = true): Collection
    {
        $persisted = $wedding->rsvpQuestions->keyBy(fn ($question) => $question->key->value);
        $questions = collect(self::DEFINITIONS)->map(function (array $definition, string $key) use ($persisted) {
            $question = $persisted->get($key);

            return [
                'key' => $key,
                'scope' => $definition['scope'],
                'enabled' => $question?->enabled ?? true,
                'required' => $question?->required ?? false,
                'label' => $question?->label ?? $definition['label'],
                'helperText' => $question?->helper_text ?? $definition['helperText'],
                'sortOrder' => $question?->sort_order ?? $definition['sortOrder'],
                'system' => false,
            ];
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
        $questions = $this->questions($wedding);

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
        });

        $wedding->unsetRelation('rsvpQuestions');
    }

    public static function supportedKeys(): array
    {
        return array_column(RsvpQuestionKey::cases(), 'value');
    }
}
