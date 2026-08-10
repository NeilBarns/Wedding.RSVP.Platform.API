<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->string('meal_choice', 80)->nullable()->after('accessibility_requirements');
        });

        Schema::create('wedding_rsvp_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('question_key', 50);
            $table->string('label', 150);
            $table->string('value', 80);
            $table->unsignedInteger('sort_order');
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['wedding_id', 'question_key', 'value'], 'rsvp_option_wedding_key_value_unique');
            $table->index(['wedding_id', 'question_key', 'sort_order'], 'rsvp_option_wedding_key_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wedding_rsvp_question_options');

        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('meal_choice');
        });
    }
};
