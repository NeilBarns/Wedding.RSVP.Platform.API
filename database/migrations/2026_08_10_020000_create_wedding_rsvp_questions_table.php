<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wedding_rsvp_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('key', 50);
            $table->string('scope', 20);
            $table->boolean('enabled');
            $table->boolean('required');
            $table->string('label', 150);
            $table->string('helper_text', 500)->nullable();
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->unique(['wedding_id', 'key']);
            $table->index(['wedding_id', 'scope', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wedding_rsvp_questions');
    }
};
