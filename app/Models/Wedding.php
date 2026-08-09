<?php

namespace App\Models;

use Database\Factories\WeddingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Wedding extends Model
{
    /** @use HasFactory<WeddingFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'partner_one_name',
        'partner_two_name',
        'wedding_date',
        'rsvp_deadline',
        'dress_code',
        'status',
        'template_key',
        'theme_key',
        'primary_color',
        'secondary_color',
        'accent_color',
        'background_color',
        'heading_font',
        'body_font',
    ];

    protected function casts(): array
    {
        return [
            'wedding_date' => 'date',
            'rsvp_deadline' => 'date',
        ];
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function heroContent(): HasOne
    {
        return $this->hasOne(WeddingHeroContent::class);
    }

    public function storyEntries(): HasMany
    {
        return $this->hasMany(WeddingStoryEntry::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(WeddingEvent::class);
    }

    public function faqEntries(): HasMany
    {
        return $this->hasMany(WeddingFaqEntry::class);
    }

    public function galleryEntries(): HasMany
    {
        return $this->hasMany(WeddingGalleryEntry::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(WeddingMedia::class);
    }

    public static function currentSingleWedding(): ?self
    {
        return self::query()->oldest('id')->first();
    }
}
