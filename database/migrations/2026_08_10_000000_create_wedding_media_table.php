<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wedding_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path', 2048)->unique();
            $table->string('original_file_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('alt_text', 500)->nullable();
            $table->timestamps();
            $table->index(['wedding_id', 'created_at']);
        });

        Schema::table('wedding_hero_contents', function (Blueprint $table) {
            $table->foreignId('hero_media_id')->nullable()->after('hero_media_url')->constrained('wedding_media')->restrictOnDelete();
        });
        Schema::table('wedding_story_entries', function (Blueprint $table) {
            $table->foreignId('image_media_id')->nullable()->after('image_url')->constrained('wedding_media')->restrictOnDelete();
        });
        Schema::table('wedding_gallery_entries', function (Blueprint $table) {
            $table->string('image_url', 2048)->nullable()->change();
            $table->foreignId('media_id')->nullable()->after('image_url')->constrained('wedding_media')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wedding_gallery_entries', fn (Blueprint $table) => $table->dropConstrainedForeignId('media_id'));
        Schema::table('wedding_story_entries', fn (Blueprint $table) => $table->dropConstrainedForeignId('image_media_id'));
        Schema::table('wedding_hero_contents', fn (Blueprint $table) => $table->dropConstrainedForeignId('hero_media_id'));
        Schema::dropIfExists('wedding_media');
    }
};
