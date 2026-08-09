<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingHeroContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'eyebrow' => $this->eyebrow, 'headline' => $this->headline, 'subheadline' => $this->subheadline, 'heroMediaId' => $this->hero_media_id, 'mediaUrl' => $this->media?->url() ?? $this->hero_media_url, 'mediaAltText' => $this->hero_media_alt_text ?? $this->media?->alt_text, 'isPublished' => $this->is_published];
    }
}
