<?php

namespace App\Models;

use Database\Factories\WeddingHeroContentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeddingHeroContent extends Model
{
    /** @use HasFactory<WeddingHeroContentFactory> */
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
        return $this->belongsTo(WeddingMedia::class, 'hero_media_id');
    }
}
