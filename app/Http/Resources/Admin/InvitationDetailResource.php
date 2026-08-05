<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;

class InvitationDetailResource extends InvitationListResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'internalNotes' => $this->internal_notes,
            'invitationUrl' => null,
            'guests' => GuestResource::collection($this->whenLoaded('guests')),
        ];
    }
}
