<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wedding;
use App\Models\WeddingMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminWeddingMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['filesystems.wedding_media_disk' => 'public']);
    }

    public function test_upload_requires_authentication_and_both_admin_roles_can_upload_images(): void
    {
        $wedding = Wedding::factory()->create();
        $this->withHeader('Accept', 'application/json')
            ->post('/api/admin/wedding-media', ['file' => $this->image('photo.jpg', 'jpeg', 800, 600)])
            ->assertUnauthorized();

        foreach ([User::ROLE_OWNER, User::ROLE_ADMINISTRATOR] as $index => $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $response = $this->post('/api/admin/wedding-media', [
                'file' => $this->image("original-$index.png", 'png', 900, 700),
                'altText' => '  Wedding portrait  ',
            ])->assertCreated()->assertJsonPath('data.altText', 'Wedding portrait');
            $media = WeddingMedia::query()->findOrFail($response->json('data.id'));
            $this->assertSame($wedding->id, $media->wedding_id);
            $this->assertSame("original-$index.png", $media->original_file_name);
            $this->assertSame(900, $media->width);
            $this->assertSame(700, $media->height);
            $this->assertMatchesRegularExpression("#^weddings/{$wedding->id}/media/[0-9a-f-]+\\.(png|jpg)$#", $media->path);
            $this->assertStringNotContainsString('original-', $media->path);
            Storage::disk('public')->assertExists($media->path);
        }
    }

    public function test_jpeg_png_and_webp_content_are_accepted_and_metadata_is_persisted(): void
    {
        Wedding::factory()->create();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $files = [
            $this->image('image.jpg', 'jpeg', 800, 600),
            $this->image('image.png', 'png', 800, 600),
            $this->image('image.webp', 'webp', 800, 600),
        ];
        foreach ($files as $file) {
            $this->post('/api/admin/wedding-media', ['file' => $file])->assertCreated();
        }

        $this->assertSame(['image/jpeg', 'image/png', 'image/webp'], WeddingMedia::query()->orderBy('id')->pluck('mime_type')->all());
        $this->assertTrue(WeddingMedia::query()->get()->every(fn (WeddingMedia $media) => $media->file_size_bytes > 0));
    }

    public function test_upload_validation_rejects_unsupported_oversized_and_small_files(): void
    {
        Wedding::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->withHeader('Accept', 'application/json')->post('/api/admin/wedding-media', ['file' => UploadedFile::fake()->create('vector.svg', 10, 'image/svg+xml')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->withHeader('Accept', 'application/json')->post('/api/admin/wedding-media', ['file' => UploadedFile::fake()->create('large.jpg', 10 * 1024 + 1, 'image/jpeg')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->withHeader('Accept', 'application/json')->post('/api/admin/wedding-media', ['file' => $this->image('small.jpg', 'jpeg', 599, 399)])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('wedding_media', 0);
    }

    public function test_list_detail_update_and_delete_are_current_wedding_scoped(): void
    {
        $current = Wedding::factory()->create();
        $other = Wedding::factory()->create();
        $mine = $this->media($current, 'mine.jpg');
        $otherMedia = $this->media($other, 'other.jpg');
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/admin/wedding-media?perPage=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.per_page', 1)->assertJsonPath('data.0.id', $mine->id);
        $this->getJson("/api/admin/wedding-media/{$mine->id}")->assertOk()->assertJsonPath('data.originalFileName', 'mine.jpg');
        $this->getJson("/api/admin/wedding-media/{$otherMedia->id}")->assertNotFound();
        $this->putJson("/api/admin/wedding-media/{$mine->id}", ['altText' => ' Updated alt ', 'path' => 'unsafe'])->assertOk()->assertJsonPath('data.altText', 'Updated alt');
        $this->assertSame('weddings/'.$current->id.'/media/mine.jpg', $mine->refresh()->path);
        $this->deleteJson("/api/admin/wedding-media/{$otherMedia->id}")->assertNotFound();
        $this->deleteJson("/api/admin/wedding-media/{$mine->id}")->assertNoContent();
        Storage::disk('public')->assertMissing($mine->path);
        $this->assertDatabaseMissing('wedding_media', ['id' => $mine->id]);
    }

    public function test_media_links_resolve_urls_alt_precedence_cross_wedding_validation_and_delete_conflicts(): void
    {
        $wedding = Wedding::factory()->create(['status' => Wedding::STATUS_PUBLISHED]);
        $other = Wedding::factory()->create();
        $media = $this->media($wedding, 'shared.jpg', 'Media fallback alt');
        $foreign = $this->media($other, 'foreign.jpg');
        $this->actingAs(User::factory()->create());

        $heroPayload = ['eyebrow' => null, 'headline' => 'Hero', 'subheadline' => null, 'mediaUrl' => 'https://legacy.example/hero.jpg', 'heroMediaId' => $media->id, 'mediaAltText' => 'Hero-specific alt', 'isPublished' => true];
        $this->putJson('/api/admin/wedding-content/hero', $heroPayload)->assertCreated()->assertJsonPath('data.heroMediaId', $media->id)->assertJsonPath('data.mediaAltText', 'Hero-specific alt');
        $story = $this->postJson('/api/admin/wedding-content/story', ['title' => 'Story', 'body' => 'Body', 'imageUrl' => null, 'imageMediaId' => $media->id, 'imageAltText' => null, 'eventDate' => null, 'sortOrder' => 0, 'isPublished' => true])->assertCreated()->assertJsonPath('data.imageAltText', 'Media fallback alt');
        $gallery = $this->postJson('/api/admin/wedding-content/gallery', ['imageUrl' => null, 'mediaId' => $media->id, 'altText' => null, 'caption' => 'Gallery', 'sortOrder' => 0, 'isPublished' => true])->assertCreated()->assertJsonPath('data.altText', 'Media fallback alt');

        $this->postJson('/api/admin/wedding-content/gallery', ['imageUrl' => null, 'mediaId' => $foreign->id, 'altText' => null, 'caption' => null, 'sortOrder' => 1, 'isPublished' => false])->assertUnprocessable()->assertJsonValidationErrors('mediaId');
        $this->getJson('/api/wedding')->assertOk()->assertJsonPath('data.content.hero.mediaUrl', $media->url())->assertJsonPath('data.content.hero.mediaAltText', 'Hero-specific alt')->assertJsonPath('data.content.story.0.imageUrl', $media->url())->assertJsonPath('data.content.gallery.0.imageUrl', $media->url())->assertJsonMissingPath('data.content.hero.heroMediaId')->assertJsonMissingPath('data.content.story.0.imageMediaId')->assertJsonMissingPath('data.content.gallery.0.mediaId');
        $this->deleteJson("/api/admin/wedding-media/{$media->id}")->assertConflict()->assertJsonPath('code', 'media_in_use');
        Storage::disk('public')->assertExists($media->path);

        $this->putJson('/api/admin/wedding-content/hero', [...$heroPayload, 'heroMediaId' => null])->assertOk()->assertJsonPath('data.mediaUrl', 'https://legacy.example/hero.jpg');
        $this->deleteJson('/api/admin/wedding-content/story/'.$story->json('data.id'))->assertNoContent();
        $this->deleteJson('/api/admin/wedding-content/gallery/'.$gallery->json('data.id'))->assertNoContent();
        Storage::disk('public')->delete($media->path);
        $this->deleteJson("/api/admin/wedding-media/{$media->id}")->assertNoContent();
    }

    private function media(Wedding $wedding, string $name, ?string $alt = null): WeddingMedia
    {
        $path = "weddings/{$wedding->id}/media/$name";
        Storage::disk('public')->put($path, 'image');

        return $wedding->media()->create(['disk' => 'public', 'path' => $path, 'original_file_name' => $name, 'mime_type' => 'image/jpeg', 'file_size_bytes' => 5, 'width' => 800, 'height' => 600, 'alt_text' => $alt]);
    }

    private function image(string $name, string $format, int $width, int $height): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'wedding-media-');
        $bytes = match ($format) {
            'jpeg' => hex2bin('ffd8ffe000104a46494600010100000100010000ffc0001108'.sprintf('%04x%04x', $height, $width).'03011100021100031100ffd9'),
            'png' => $this->png($width, $height),
            'webp' => 'RIFF'.pack('V', 22).'WEBPVP8X'.pack('V', 10)."\0\0\0\0".$this->uint24($width - 1).$this->uint24($height - 1),
        };
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function png(int $width, int $height): string
    {
        $chunk = function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('H*', hash('crc32b', $type.$data));
        };
        $rows = str_repeat("\0".str_repeat("\0", $width * 3), $height);

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0)).$chunk('IDAT', gzcompress($rows, 1)).$chunk('IEND', '');
    }

    private function uint24(int $value): string
    {
        return chr($value & 255).chr(($value >> 8) & 255).chr(($value >> 16) & 255);
    }
}
