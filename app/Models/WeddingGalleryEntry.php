<?php

namespace App\Models;

use Database\Factories\WeddingGalleryEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeddingGalleryEntry extends Model
{
    /** @use HasFactory<WeddingGalleryEntryFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(WeddingMedia::class, 'media_id');
    }
}
