<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class WeddingMedia extends Model
{
    protected $table = 'wedding_media';

    protected $fillable = ['wedding_id', 'disk', 'path', 'original_file_name', 'mime_type', 'file_size_bytes', 'width', 'height', 'alt_text'];

    protected function casts(): array
    {
        return ['file_size_bytes' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function heroContents(): HasMany
    {
        return $this->hasMany(WeddingHeroContent::class, 'hero_media_id');
    }

    public function storyEntries(): HasMany
    {
        return $this->hasMany(WeddingStoryEntry::class, 'image_media_id');
    }

    public function galleryEntries(): HasMany
    {
        return $this->hasMany(WeddingGalleryEntry::class, 'media_id');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function isReferenced(): bool
    {
        return $this->heroContents()->exists() || $this->storyEntries()->exists() || $this->galleryEntries()->exists();
    }
}
