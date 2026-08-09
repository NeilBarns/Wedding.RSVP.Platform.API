<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingGalleryEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'mediaId' => $this->media_id, 'imageUrl' => $this->media?->url() ?? $this->image_url, 'altText' => $this->alt_text ?? $this->media?->alt_text, 'caption' => $this->caption, 'sortOrder' => $this->sort_order, 'isPublished' => $this->is_published];
    }
}
