<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'invitation' => [
                'id' => $this->id,
                'displayName' => $this->display_name,
                'status' => $this->status,
                'canRespond' => $this->canRespond(),
                'hasSubmitted' => $this->submitted_at !== null,
                'isLocked' => $this->isLockedForResponse(),
                'firstOpenedAt' => $this->first_opened_at?->toIso8601String(),
                'lastOpenedAt' => $this->last_opened_at?->toIso8601String(),
                'submittedAt' => $this->submitted_at?->toIso8601String(),
                'responseContactNumber' => $this->response_contact_number,
                'responseEmail' => $this->response_email,
                'messageToCouple' => $this->message_to_couple,
                'guests' => PublicInvitationGuestResource::collection($this->whenLoaded('guests')),
            ],
            'wedding' => (new WeddingSummaryResource($this->wedding))->resolve($request),
        ];
    }
}
