<?php

namespace App\Actions;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Wedding;
use App\Support\InvitationAccessToken;
use Illuminate\Support\Facades\DB;

class CreateInvitation
{
    public function __construct(private InvitationAccessToken $tokens) {}

    public function handle(Wedding $wedding, array $data): array
    {
        return DB::transaction(function () use ($wedding, $data) {
            $rawToken = $this->tokens->generate();
            $invitation = $wedding->invitations()->create([
                ...$this->profile($data),
                'token_hash' => $this->tokens->hash($rawToken),
                'status' => Invitation::STATUS_DRAFT,
            ]);

            foreach ($data['guests'] as $guest) {
                $invitation->guests()->create([
                    ...$this->guest($guest),
                    'attendance_status' => Guest::ATTENDANCE_PENDING,
                ]);
            }

            return [$invitation, $rawToken];
        });
    }

    public function profile(array $data): array
    {
        return [
            'display_name' => $data['displayName'],
            'contact_person_name' => $data['contactPersonName'] ?? null,
            'contact_number' => $data['contactNumber'] ?? null,
            'email' => $data['email'] ?? null,
            'internal_notes' => $data['internalNotes'] ?? null,
        ];
    }

    public function guest(array $data): array
    {
        return [
            'full_name' => $data['fullName'],
            'guest_type' => $data['guestType'],
            'sort_order' => $data['sortOrder'],
            'dietary_requirements' => $data['dietaryRequirements'] ?? null,
            'accessibility_requirements' => $data['accessibilityRequirements'] ?? null,
        ];
    }
}
