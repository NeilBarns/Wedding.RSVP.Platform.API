<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'partnerOneName' => $this->partner_one_name,
            'partnerTwoName' => $this->partner_two_name,
            'weddingDate' => $this->wedding_date->format('Y-m-d'),
            'rsvpDeadline' => $this->rsvp_deadline?->format('Y-m-d'),
            'dressCode' => $this->dress_code,
            'status' => $this->status,
            'theme' => [
                'key' => $this->theme_key,
                'primaryColor' => $this->primary_color,
                'secondaryColor' => $this->secondary_color,
                'accentColor' => $this->accent_color,
                'backgroundColor' => $this->background_color,
                'headingFont' => $this->heading_font,
                'bodyFont' => $this->body_font,
            ],
        ];
    }
}
