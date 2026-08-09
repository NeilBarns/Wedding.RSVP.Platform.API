<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wedding;
use App\Models\WeddingEvent;
use App\Models\WeddingFaqEntry;
use App\Models\WeddingGalleryEntry;
use App\Models\WeddingStoryEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWeddingContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_routes_require_authentication_and_both_admin_roles_can_manage_hero(): void
    {
        Wedding::factory()->create();
        $this->getJson('/api/admin/wedding-content/hero')->assertUnauthorized();
        $this->postJson('/api/admin/wedding-content/story', [])->assertUnauthorized();
        foreach ([User::ROLE_OWNER, User::ROLE_ADMINISTRATOR] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->putJson('/api/admin/wedding-content/hero', ['eyebrow' => '  Welcome  ', 'headline' => null, 'subheadline' => null, 'mediaUrl' => null, 'mediaAltText' => null, 'isPublished' => true, 'weddingId' => 999])
                ->assertSuccessful()->assertJsonPath('data.eyebrow', 'Welcome')->assertJsonPath('data.isPublished', true);
        }
        $this->getJson('/api/admin/wedding-content/hero')->assertOk()->assertJsonPath('data.eyebrow', 'Welcome');
        $this->assertDatabaseCount('wedding_hero_contents', 1);
    }

    public function test_all_ordered_content_types_support_current_wedding_crud_and_scope(): void
    {
        $wedding = Wedding::factory()->create();
        $other = Wedding::factory()->create();
        $this->actingAs(User::factory()->create());
        $cases = [
            ['story', WeddingStoryEntry::factory()->for($other)->create()->id, ['title' => 'Story', 'body' => 'Plain story', 'imageUrl' => null, 'imageAltText' => null, 'eventDate' => null, 'sortOrder' => 2, 'isPublished' => true], 'title'],
            ['events', WeddingEvent::factory()->for($other)->create()->id, ['title' => 'Ceremony', 'eventType' => 'ceremony', 'eventDate' => '2026-12-22', 'startTime' => '15:00', 'endTime' => '16:00', 'venueName' => null, 'addressLine' => null, 'mapUrl' => null, 'description' => null, 'dressCodeOverride' => null, 'sortOrder' => 2, 'isPublished' => true], 'title'],
            ['faq', WeddingFaqEntry::factory()->for($other)->create()->id, ['question' => 'Question?', 'answer' => 'Answer.', 'sortOrder' => 2, 'isPublished' => true], 'question'],
            ['gallery', WeddingGalleryEntry::factory()->for($other)->create()->id, ['imageUrl' => 'https://example.test/image.jpg', 'altText' => 'Image', 'caption' => 'Caption', 'sortOrder' => 2, 'isPublished' => true], 'caption'],
        ];
        foreach ($cases as [$path, $otherId, $payload, $field]) {
            $this->getJson("/api/admin/wedding-content/$path/$otherId")->assertNotFound();
            $id = $this->postJson("/api/admin/wedding-content/$path", [...$payload, 'weddingId' => $other->id])->assertCreated()->json('data.id');
            $this->putJson("/api/admin/wedding-content/$path/$id", [...$payload, $field => 'Updated', 'sortOrder' => 1])->assertOk()->assertJsonPath("data.$field", 'Updated');
            $this->getJson("/api/admin/wedding-content/$path")->assertOk()->assertJsonPath("data.0.$field", 'Updated');
            $this->deleteJson("/api/admin/wedding-content/$path/$id")->assertNoContent();
        }
        $this->assertDatabaseCount('wedding_story_entries', 1);
        $this->assertSame($wedding->id, Wedding::currentSingleWedding()->id);
    }

    public function test_validation_rejects_invalid_events_html_urls_and_negative_sort_order(): void
    {
        Wedding::factory()->create();
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/admin/wedding-content/events', ['title' => '<b>Ceremony</b>', 'eventType' => 'party', 'eventDate' => 'bad', 'startTime' => '16:00', 'endTime' => '16:00', 'sortOrder' => -1, 'isPublished' => true])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'eventType', 'eventDate', 'endTime', 'sortOrder']);
        $this->postJson('/api/admin/wedding-content/gallery', ['imageUrl' => 'not-url', 'sortOrder' => 0, 'isPublished' => false])->assertUnprocessable()->assertJsonValidationErrors('imageUrl');
    }
}
