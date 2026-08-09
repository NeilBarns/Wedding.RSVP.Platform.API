<?php

namespace App\Services;

class WeddingContentMapper
{
    public function hero(array $v): array
    {
        return ['eyebrow' => $v['eyebrow'] ?? null, 'headline' => $v['headline'] ?? null, 'subheadline' => $v['subheadline'] ?? null, 'hero_media_url' => $v['mediaUrl'] ?? null, 'hero_media_id' => $v['heroMediaId'] ?? null, 'hero_media_alt_text' => $v['mediaAltText'] ?? null, 'is_published' => $v['isPublished']];
    }

    public function story(array $v): array
    {
        return ['title' => $v['title'] ?? null, 'body' => $v['body'], 'image_url' => $v['imageUrl'] ?? null, 'image_media_id' => $v['imageMediaId'] ?? null, 'image_alt_text' => $v['imageAltText'] ?? null, 'event_date' => $v['eventDate'] ?? null, 'sort_order' => $v['sortOrder'], 'is_published' => $v['isPublished']];
    }

    public function event(array $v): array
    {
        return ['title' => $v['title'], 'event_type' => $v['eventType'], 'event_date' => $v['eventDate'], 'start_time' => $v['startTime'] ?? null, 'end_time' => $v['endTime'] ?? null, 'venue_name' => $v['venueName'] ?? null, 'address_line' => $v['addressLine'] ?? null, 'map_url' => $v['mapUrl'] ?? null, 'description' => $v['description'] ?? null, 'dress_code_override' => $v['dressCodeOverride'] ?? null, 'sort_order' => $v['sortOrder'], 'is_published' => $v['isPublished']];
    }

    public function faq(array $v): array
    {
        return ['question' => $v['question'], 'answer' => $v['answer'], 'sort_order' => $v['sortOrder'], 'is_published' => $v['isPublished']];
    }

    public function gallery(array $v): array
    {
        return ['image_url' => $v['imageUrl'] ?? null, 'media_id' => $v['mediaId'] ?? null, 'alt_text' => $v['altText'] ?? null, 'caption' => $v['caption'] ?? null, 'sort_order' => $v['sortOrder'], 'is_published' => $v['isPublished']];
    }
}
