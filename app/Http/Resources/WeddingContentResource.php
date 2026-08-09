<?php

namespace App\Http\Resources;

use App\Http\Resources\Admin\WeddingEventResource;
use App\Http\Resources\Admin\WeddingFaqEntryResource;
use App\Http\Resources\Admin\WeddingGalleryEntryResource;
use App\Http\Resources\Admin\WeddingHeroContentResource;
use App\Http\Resources\Admin\WeddingStoryEntryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hero = $this->heroContent;
        $mediaIds = ['heroMediaId', 'imageMediaId', 'mediaId'];
        $public = fn ($resource) => collect($resource->resolve($request))->map(fn (array $item) => collect($item)->except(['isPublished', ...$mediaIds])->all())->all();

        return ['hero' => $hero ? collect((new WeddingHeroContentResource($hero))->resolve($request))->except(['id', 'isPublished', ...$mediaIds])->all() : null, 'story' => $public(WeddingStoryEntryResource::collection($this->storyEntries)), 'events' => $public(WeddingEventResource::collection($this->events)), 'faq' => $public(WeddingFaqEntryResource::collection($this->faqEntries)), 'gallery' => $public(WeddingGalleryEntryResource::collection($this->galleryEntries))];
    }
}
