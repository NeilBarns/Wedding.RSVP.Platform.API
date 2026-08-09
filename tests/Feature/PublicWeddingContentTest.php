<?php

namespace Tests\Feature;

use App\Models\Wedding;
use App\Models\WeddingEvent;
use App\Models\WeddingFaqEntry;
use App\Models\WeddingGalleryEntry;
use App\Models\WeddingHeroContent;
use App\Models\WeddingStoryEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWeddingContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_wedding_returns_only_published_content_in_deterministic_order(): void
    {
        $wedding = Wedding::factory()->create(['status' => Wedding::STATUS_PUBLISHED]);
        WeddingHeroContent::factory()->for($wedding)->published()->create(['eyebrow' => 'Welcome']);
        WeddingStoryEntry::factory()->for($wedding)->published()->create(['title' => 'Second', 'sort_order' => 2]);
        WeddingStoryEntry::factory()->for($wedding)->published()->create(['title' => 'First', 'sort_order' => 1]);
        WeddingStoryEntry::factory()->for($wedding)->create(['title' => 'Private']);
        WeddingEvent::factory()->for($wedding)->published()->create(['title' => 'Reception', 'sort_order' => 2]);
        WeddingEvent::factory()->for($wedding)->published()->create(['title' => 'Ceremony', 'sort_order' => 1]);
        WeddingEvent::factory()->for($wedding)->create(['title' => 'Private']);
        WeddingFaqEntry::factory()->for($wedding)->published()->create(['question' => 'Second?', 'sort_order' => 2]);
        WeddingFaqEntry::factory()->for($wedding)->published()->create(['question' => 'First?', 'sort_order' => 1]);
        WeddingFaqEntry::factory()->for($wedding)->create(['question' => 'Private?']);
        WeddingGalleryEntry::factory()->for($wedding)->published()->create(['caption' => 'Second', 'sort_order' => 2]);
        WeddingGalleryEntry::factory()->for($wedding)->published()->create(['caption' => 'First', 'sort_order' => 1]);
        WeddingGalleryEntry::factory()->for($wedding)->create(['caption' => 'Private']);

        $this->getJson('/api/wedding')->assertOk()
            ->assertJsonPath('data.content.hero.eyebrow', 'Welcome')
            ->assertJsonPath('data.content.story.0.title', 'First')
            ->assertJsonPath('data.content.story.1.title', 'Second')
            ->assertJsonPath('data.content.events.0.title', 'Ceremony')
            ->assertJsonPath('data.content.events.1.title', 'Reception')
            ->assertJsonPath('data.content.faq.0.question', 'First?')
            ->assertJsonPath('data.content.gallery.0.caption', 'First')
            ->assertJsonCount(2, 'data.content.story')->assertJsonCount(2, 'data.content.events')
            ->assertJsonCount(2, 'data.content.faq')->assertJsonCount(2, 'data.content.gallery')
            ->assertJsonMissingPath('data.content.hero.isPublished')
            ->assertJsonMissingPath('data.content.story.0.isPublished')
            ->assertJsonMissingPath('data.content.events.0.isPublished')
            ->assertJsonMissingPath('data.content.faq.0.isPublished')
            ->assertJsonMissingPath('data.content.gallery.0.isPublished')
            ->assertJsonMissing(['Private']);
    }

    public function test_unpublished_hero_and_all_content_on_non_published_weddings_are_omitted(): void
    {
        foreach ([Wedding::STATUS_DRAFT, Wedding::STATUS_ARCHIVED] as $status) {
            $wedding = Wedding::factory()->create(['status' => $status]);
            WeddingHeroContent::factory()->for($wedding)->published()->create();
            WeddingStoryEntry::factory()->for($wedding)->published()->create();
            WeddingEvent::factory()->for($wedding)->published()->create();
            WeddingFaqEntry::factory()->for($wedding)->published()->create();
            WeddingGalleryEntry::factory()->for($wedding)->published()->create();
            $this->getJson('/api/wedding')->assertOk()->assertJsonPath('data.content', ['hero' => null, 'story' => [], 'events' => [], 'faq' => [], 'gallery' => []]);
            $wedding->delete();
        }

        $wedding = Wedding::factory()->create(['status' => Wedding::STATUS_PUBLISHED]);
        WeddingHeroContent::factory()->for($wedding)->create();
        $this->getJson('/api/wedding')->assertJsonPath('data.content.hero', null);
    }
}
