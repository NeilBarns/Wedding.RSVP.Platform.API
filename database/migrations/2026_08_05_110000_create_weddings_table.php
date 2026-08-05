<?php

use App\Models\Wedding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weddings', function (Blueprint $table) {
            $table->id();
            $table->string('partner_one_name', 150);
            $table->string('partner_two_name', 150);
            $table->date('wedding_date');
            $table->date('rsvp_deadline')->nullable();
            $table->string('dress_code', 100)->nullable();
            $table->string('status', 20)->default(Wedding::STATUS_DRAFT);
            $table->string('theme_key', 100)->nullable();
            $table->string('primary_color', 20)->nullable();
            $table->string('secondary_color', 20)->nullable();
            $table->string('accent_color', 20)->nullable();
            $table->string('background_color', 20)->nullable();
            $table->string('heading_font', 100)->nullable();
            $table->string('body_font', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weddings');
    }
};
