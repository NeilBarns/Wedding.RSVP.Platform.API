<?php

namespace App\Models;

use App\Enums\RsvpQuestionKey;
use App\Enums\RsvpQuestionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeddingRsvpQuestion extends Model
{
    protected $fillable = [
        'wedding_id',
        'key',
        'scope',
        'enabled',
        'required',
        'label',
        'helper_text',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'key' => RsvpQuestionKey::class,
            'scope' => RsvpQuestionScope::class,
            'enabled' => 'boolean',
            'required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }
}
