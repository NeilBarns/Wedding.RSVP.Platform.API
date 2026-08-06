<?php

namespace App\Http\Resources;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isLocked = $this->status === Invitation::STATUS_LOCKED || $this->locked_at !== null;
        $deadlineAllowsResponse = $this->wedding->rsvp_deadline === null
            || today(config('app.timezone'))->lte($this->wedding->rsvp_deadline);
        $canRespond = in_array($this->status, [
            Invitation::STATUS_READY,
            Invitation::STATUS_SUBMITTED,
        ], true) && ! $isLocked && $deadlineAllowsResponse;

        return [
            'invitation' => [
                'id' => $this->id,
                'displayName' => $this->display_name,
                'status' => $this->status,
                'canRespond' => $canRespond,
                'hasSubmitted' => $this->submitted_at !== null,
                'isLocked' => $isLocked,
                'firstOpenedAt' => $this->first_opened_at?->toIso8601String(),
                'lastOpenedAt' => $this->last_opened_at?->toIso8601String(),
                'submittedAt' => $this->submitted_at?->toIso8601String(),
                'guests' => PublicInvitationGuestResource::collection($this->whenLoaded('guests')),
            ],
            'wedding' => (new WeddingSummaryResource($this->wedding))->resolve($request),
        ];
    }
}
