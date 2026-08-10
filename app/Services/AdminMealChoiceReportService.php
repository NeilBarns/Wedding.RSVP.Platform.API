<?php

namespace App\Services;

use App\Enums\RsvpQuestionKey;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Wedding;

class AdminMealChoiceReportService
{
    public function __construct(
        private RsvpConfigurationService $configuration,
    ) {}

    public function report(Wedding $wedding): array
    {
        $meal = $this->configuration->byKey($wedding)[RsvpQuestionKey::MealChoice->value];
        $options = $wedding->rsvpQuestionOptions
            ->where('question_key', RsvpQuestionKey::MealChoice)
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values();
        $enabledOptions = $options->where('enabled', true)->keyBy('value');
        $allOptions = $options->keyBy('value');

        $guests = Guest::query()
            ->select('guests.*')
            ->join('invitations', 'invitations.id', '=', 'guests.invitation_id')
            ->where('invitations.wedding_id', $wedding->id)
            ->where('invitations.status', '!=', Invitation::STATUS_ARCHIVED)
            ->where('guests.attendance_status', Guest::ATTENDANCE_ATTENDING)
            ->with('invitation:id,display_name')
            ->orderBy('invitations.display_name')
            ->orderBy('guests.sort_order')
            ->orderBy('guests.full_name')
            ->orderBy('guests.id')
            ->get();

        $rows = $guests->map(function (Guest $guest) use ($enabledOptions, $allOptions) {
            $value = is_string($guest->meal_choice) && trim($guest->meal_choice) !== ''
                ? $guest->meal_choice
                : null;
            $enabledOption = $value === null ? null : $enabledOptions->get($value);
            $storedOption = $value === null ? null : $allOptions->get($value);
            $status = $value === null ? 'unselected' : ($enabledOption === null ? 'stale' : 'selected');

            return [
                'guestId' => $guest->id,
                'guestName' => $guest->full_name,
                'guestType' => $guest->guest_type,
                'invitationId' => $guest->invitation_id,
                'invitationDisplayName' => $guest->invitation->display_name,
                'attendance' => $guest->attendance_status,
                'mealChoice' => [
                    'value' => $value,
                    'label' => $enabledOption?->label ?? $storedOption?->label,
                    'status' => $status,
                ],
            ];
        });

        $statusCounts = $rows->countBy('mealChoice.status');
        $selectionCounts = $rows
            ->where('mealChoice.status', 'selected')
            ->countBy('mealChoice.value');

        return [
            'configuration' => [
                'enabled' => $meal['enabled'],
                'required' => $meal['required'],
                'label' => $meal['label'],
            ],
            'summary' => [
                'attendingGuests' => $rows->count(),
                'selected' => $statusCounts->get('selected', 0),
                'unselected' => $statusCounts->get('unselected', 0),
                'stale' => $statusCounts->get('stale', 0),
            ],
            'options' => $enabledOptions->values()->map(fn ($option) => [
                'label' => $option->label,
                'value' => $option->value,
                'enabled' => true,
                'count' => $selectionCounts->get($option->value, 0),
            ])->all(),
            'guests' => $rows->all(),
        ];
    }
}
