<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wedding_hero_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('eyebrow', 150)->nullable();
            $table->string('headline')->nullable();
            $table->text('subheadline')->nullable();
            $table->string('hero_media_url', 2048)->nullable();
            $table->string('hero_media_alt_text', 500)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['wedding_id', 'is_published']);
        });
        Schema::create('wedding_story_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('body');
            $table->string('image_url', 2048)->nullable();
            $table->string('image_alt_text', 500)->nullable();
            $table->date('event_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['wedding_id', 'is_published']);
            $table->index(['wedding_id', 'sort_order']);
        });
        Schema::create('wedding_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('event_type', 20);
            $table->date('event_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('venue_name')->nullable();
            $table->text('address_line')->nullable();
            $table->string('map_url', 2048)->nullable();
            $table->text('description')->nullable();
            $table->text('dress_code_override')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['wedding_id', 'is_published']);
            $table->index(['wedding_id', 'sort_order']);
        });
        Schema::create('wedding_faq_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('question', 500);
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['wedding_id', 'is_published']);
            $table->index(['wedding_id', 'sort_order']);
        });
        Schema::create('wedding_gallery_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('image_url', 2048);
            $table->string('alt_text', 500)->nullable();
            $table->text('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['wedding_id', 'is_published']);
            $table->index(['wedding_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wedding_gallery_entries');
        Schema::dropIfExists('wedding_faq_entries');
        Schema::dropIfExists('wedding_events');
        Schema::dropIfExists('wedding_story_entries');
        Schema::dropIfExists('wedding_hero_contents');
    }
};
