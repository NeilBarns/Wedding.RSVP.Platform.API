<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'eventType' => $this->event_type, 'eventDate' => $this->event_date->format('Y-m-d'), 'startTime' => $this->start_time ? substr($this->start_time, 0, 5) : null, 'endTime' => $this->end_time ? substr($this->end_time, 0, 5) : null, 'venueName' => $this->venue_name, 'addressLine' => $this->address_line, 'mapUrl' => $this->map_url, 'description' => $this->description, 'dressCodeOverride' => $this->dress_code_override, 'sortOrder' => $this->sort_order, 'isPublished' => $this->is_published];
    }
}
