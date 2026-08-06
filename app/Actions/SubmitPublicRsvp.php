<?php

namespace App\Actions;

use App\Exceptions\RsvpUnavailableException;
use App\Models\Guest;
use App\Models\Invitation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitPublicRsvp
{
    public function __construct(private ResolvePublicInvitation $resolveInvitation) {}

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
            $responsesById = $responses->keyBy('id');
            $submittedAt = now();

            foreach ($guests as $guest) {
                $response = $responsesById->get($guest->id);
                $attending = $response['attendanceStatus'] === Guest::ATTENDANCE_ATTENDING;

                $guest->update([
                    'attendance_status' => $response['attendanceStatus'],
                    'dietary_requirements' => $attending
                        ? ($response['dietaryRequirements'] ?? null)
                        : null,
                    'accessibility_requirements' => $attending
                        ? ($response['accessibilityRequirements'] ?? null)
                        : null,
                ]);
            }

            $invitation->update([
                'status' => Invitation::STATUS_SUBMITTED,
                'submitted_at' => $submittedAt,
                'response_contact_number' => $data['contactNumber'] ?? null,
                'response_email' => $data['email'] ?? null,
                'message_to_couple' => $data['message'] ?? null,
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
