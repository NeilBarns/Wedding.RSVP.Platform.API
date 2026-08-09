<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingStoryEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'body' => $this->body, 'imageMediaId' => $this->image_media_id, 'imageUrl' => $this->media?->url() ?? $this->image_url, 'imageAltText' => $this->image_alt_text ?? $this->media?->alt_text, 'eventDate' => $this->event_date?->format('Y-m-d'), 'sortOrder' => $this->sort_order, 'isPublished' => $this->is_published];
    }
}
