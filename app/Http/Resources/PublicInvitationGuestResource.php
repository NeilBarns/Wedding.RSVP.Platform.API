<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicInvitationGuestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fullName' => $this->full_name,
            'guestType' => $this->guest_type,
            'attendanceStatus' => $this->attendance_status,
            'dietaryRequirements' => $this->dietary_requirements,
            'accessibilityRequirements' => $this->accessibility_requirements,
            'sortOrder' => $this->sort_order,
        ];
    }
}
