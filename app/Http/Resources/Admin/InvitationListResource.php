<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'displayName' => $this->display_name,
            'contactPersonName' => $this->contact_person_name,
            'contactNumber' => $this->contact_number,
            'email' => $this->email,
            'status' => $this->status,
            'guestCount' => $this->guest_count,
            'attendingCount' => $this->attending_count,
            'declinedCount' => $this->declined_count,
            'pendingCount' => $this->pending_count,
            'firstOpenedAt' => $this->first_opened_at?->toIso8601String(),
            'lastOpenedAt' => $this->last_opened_at?->toIso8601String(),
            'submittedAt' => $this->submitted_at?->toIso8601String(),
            'lockedAt' => $this->locked_at?->toIso8601String(),
            'createdAt' => $this->created_at->toIso8601String(),
            'updatedAt' => $this->updated_at->toIso8601String(),
        ];
    }
}
