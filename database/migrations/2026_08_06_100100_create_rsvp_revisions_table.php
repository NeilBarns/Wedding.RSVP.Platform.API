<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsvp_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->json('response_snapshot');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['invitation_id', 'revision_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsvp_revisions');
    }
};
