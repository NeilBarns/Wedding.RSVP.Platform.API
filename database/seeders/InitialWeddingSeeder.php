<?php

namespace Database\Seeders;

use App\Models\Wedding;
use Illuminate\Database\Seeder;

class InitialWeddingSeeder extends Seeder
{
    public function run(): void
    {
        Wedding::query()->updateOrCreate(
            [
                'partner_one_name' => 'Neil Barnedo',
                'partner_two_name' => 'Hazel Elago',
                'wedding_date' => '2026-12-22',
            ],
            [
                'rsvp_deadline' => null,
                'dress_code' => 'Filipiniana',
                'status' => Wedding::STATUS_DRAFT,
                'theme_key' => null,
                'primary_color' => null,
                'secondary_color' => null,
                'accent_color' => null,
                'background_color' => null,
                'heading_font' => null,
                'body_font' => null,
            ],
        );
    }
}
