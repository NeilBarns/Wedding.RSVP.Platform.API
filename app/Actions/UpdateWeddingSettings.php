<?php

namespace App\Actions;

use App\Models\Wedding;

class UpdateWeddingSettings
{
    public function handle(Wedding $wedding, array $settings): Wedding
    {
        $theme = $settings['theme'] ?? [];

        $attributes = [
            'partner_one_name' => $settings['partnerOneName'],
            'partner_two_name' => $settings['partnerTwoName'],
            'wedding_date' => $settings['weddingDate'],
            'rsvp_deadline' => $settings['rsvpDeadline'] ?? null,
            'dress_code' => $settings['dressCode'] ?? null,
            'status' => $settings['status'],
            'theme_key' => $theme['key'] ?? null,
            'primary_color' => $theme['primaryColor'] ?? null,
            'secondary_color' => $theme['secondaryColor'] ?? null,
            'accent_color' => $theme['accentColor'] ?? null,
            'background_color' => $theme['backgroundColor'] ?? null,
            'heading_font' => $theme['headingFont'] ?? null,
            'body_font' => $theme['bodyFont'] ?? null,
        ];

        // Older full-PUT clients do not know about templateKey. In that case,
        // preserve the presentation family already assigned to the wedding.
        if (array_key_exists('templateKey', $settings)) {
            $attributes['template_key'] = $settings['templateKey'];
        }

        $wedding->update($attributes);

        return $wedding->refresh();
    }
}
