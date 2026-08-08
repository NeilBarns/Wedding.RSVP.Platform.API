<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicRsvpConfirmationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $invitation = $this->resource['invitation'];
        $revision = $this->resource['revision'];

        return [
            'invitation' => [
                'id' => $invitation->id,
                'displayName' => $invitation->display_name,
                'status' => $invitation->status,
                'canRespond' => $invitation->canRespond(),
                'hasSubmitted' => $invitation->submitted_at !== null,
                'isLocked' => $invitation->isLockedForResponse(),
                'submittedAt' => $invitation->submitted_at?->toIso8601String(),
                'responseContactNumber' => $invitation->response_contact_number,
                'responseEmail' => $invitation->response_email,
                'messageToCouple' => $invitation->message_to_couple,
                'guests' => PublicInvitationGuestResource::collection($invitation->guests),
            ],
            'revisionNumber' => $revision->revision_number,
        ];
    }
}
