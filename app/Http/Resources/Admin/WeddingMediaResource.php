<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'url' => $this->url(), 'originalFileName' => $this->original_file_name, 'mimeType' => $this->mime_type, 'fileSizeBytes' => $this->file_size_bytes, 'width' => $this->width, 'height' => $this->height, 'altText' => $this->alt_text, 'createdAt' => $this->created_at?->toISOString()];
    }
}
