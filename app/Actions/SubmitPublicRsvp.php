<?php

namespace App\Actions;

use App\Exceptions\RsvpUnavailableException;
use App\Models\Guest;
use App\Models\Invitation;
use App\Services\RsvpConfigurationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SubmitPublicRsvp
{
    public function __construct(
        private ResolvePublicInvitation $resolveInvitation,
        private RsvpConfigurationService $configuration,
    ) {}

    public function handle(string $rawToken, array $data): ?array
    {
        return DB::transaction(function () use ($rawToken, $data) {
            $invitation = $this->resolveInvitation->handleForUpdate($rawToken);

            if ($invitation === null) {
                return null;
            }

            if (! $invitation->canRespond()) {
                throw new RsvpUnavailableException;
            }

            $guests = $invitation->guests()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $responses = collect($data['guests']);
            $this->validateHousehold($guests, $responses);
            $questions = $this->configuration->byKey($invitation->wedding);
            $this->validateEnabledQuestionFormats($data, $questions);
            $this->validateConfiguredQuestions($responses, $data, $questions);
            $responsesById = $responses->keyBy('id');
            $submittedAt = now();

            foreach ($guests as $guest) {
                $response = $responsesById->get($guest->id);
                $attending = $response['attendanceStatus'] === Guest::ATTENDANCE_ATTENDING;

                $guest->update([
                    'attendance_status' => $response['attendanceStatus'],
                    'dietary_requirements' => $attending && $questions['dietaryRequirements']['enabled']
                        ? ($response['dietaryRequirements'] ?? null)
                        : null,
                    'accessibility_requirements' => $attending && $questions['accessibilityNeeds']['enabled']
                        ? ($response['accessibilityRequirements'] ?? null)
                        : null,
                ]);
            }

            $invitation->update([
                'status' => Invitation::STATUS_SUBMITTED,
                'submitted_at' => $submittedAt,
                'response_contact_number' => $questions['responsePhone']['enabled'] ? ($data['contactNumber'] ?? null) : null,
                'response_email' => $questions['responseEmail']['enabled'] ? ($data['email'] ?? null) : null,
                'message_to_couple' => $questions['messageToCouple']['enabled'] ? ($data['message'] ?? null) : null,
            ]);

            $guests = $invitation->guests()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
            $invitation->setRelation('guests', $guests);

            $revisionNumber = ((int) $invitation->rsvpRevisions()->max('revision_number')) + 1;
            $revision = $invitation->rsvpRevisions()->create([
                'revision_number' => $revisionNumber,
                'response_snapshot' => $this->snapshot($invitation, $guests),
                'submitted_at' => $submittedAt,
            ]);

            return [$invitation, $revision];
        });
    }

    private function validateHousehold(Collection $guests, Collection $responses): void
    {
        $currentIds = $guests->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
        $submittedIds = $responses->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();

        if ($currentIds->all() !== $submittedIds->all()) {
            throw ValidationException::withMessages([
                'guests' => [
                    'The guests list must contain exactly one response for every current invited guest.',
                ],
            ]);
        }
    }

    private function validateConfiguredQuestions(Collection $responses, array $data, Collection $questions): void
    {
        $errors = [];

        foreach ($responses as $index => $response) {
            if (($response['attendanceStatus'] ?? null) !== Guest::ATTENDANCE_ATTENDING) {
                continue;
            }

            foreach ([
                'dietaryRequirements' => 'dietaryRequirements',
                'accessibilityNeeds' => 'accessibilityRequirements',
            ] as $questionKey => $field) {
                $question = $questions[$questionKey];

                if ($question['enabled'] && $question['required'] && empty($response[$field])) {
                    $errors["guests.{$index}.{$field}"][] = "The {$question['label']} field is required for attending guests.";
                }
            }
        }

        foreach ([
            'responsePhone' => 'contactNumber',
            'responseEmail' => 'email',
            'messageToCouple' => 'message',
        ] as $questionKey => $field) {
            $question = $questions[$questionKey];

            if ($question['enabled'] && $question['required'] && empty($data[$field])) {
                $errors[$field][] = "The {$question['label']} field is required.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function validateEnabledQuestionFormats(array $data, Collection $questions): void
    {
        $rules = [];

        if ($questions['dietaryRequirements']['enabled']) {
            $rules['guests.*.dietaryRequirements'] = ['nullable', 'string', 'max:2000'];
        }

        if ($questions['accessibilityNeeds']['enabled']) {
            $rules['guests.*.accessibilityRequirements'] = ['nullable', 'string', 'max:2000'];
        }

        if ($questions['responsePhone']['enabled']) {
            $rules['contactNumber'] = ['nullable', 'string', 'max:30'];
        }

        if ($questions['responseEmail']['enabled']) {
            $rules['email'] = ['nullable', 'email', 'max:254'];
        }

        if ($questions['messageToCouple']['enabled']) {
            $rules['message'] = ['nullable', 'string', 'max:5000'];
        }

        Validator::make($data, $rules)->validate();
    }

    private function snapshot(Invitation $invitation, Collection $guests): array
    {
        return [
            'invitation' => [
                'id' => $invitation->id,
                'status' => $invitation->status,
                'responseContactNumber' => $invitation->response_contact_number,
                'responseEmail' => $invitation->response_email,
                'messageToCouple' => $invitation->message_to_couple,
            ],
            'guests' => $guests->map(fn (Guest $guest) => [
                'id' => $guest->id,
                'fullName' => $guest->full_name,
                'guestType' => $guest->guest_type,
                'attendanceStatus' => $guest->attendance_status,
                'dietaryRequirements' => $guest->dietary_requirements,
                'accessibilityRequirements' => $guest->accessibility_requirements,
                'sortOrder' => $guest->sort_order,
            ])->all(),
        ];
    }
}
