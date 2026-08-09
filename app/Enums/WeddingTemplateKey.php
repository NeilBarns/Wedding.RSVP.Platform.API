<?php

namespace App\Enums;

/**
 * Selects the guest-facing presentation family across the wedding site,
 * invitation, RSVP, and confirmation experiences.
 */
enum WeddingTemplateKey: string
{
    case EditorialLinenV1 = 'editorial-linen-v1';
    case ModernMinimalV1 = 'modern-minimal-v1';
}
