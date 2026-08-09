<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingFaqEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'question' => $this->question, 'answer' => $this->answer, 'sortOrder' => $this->sort_order, 'isPublished' => $this->is_published];
    }
}
