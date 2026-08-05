<?php

use App\Models\Guest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
            $table->string('full_name', 150);
            $table->string('guest_type', 30)->default(Guest::TYPE_ADULT);
            $table->string('attendance_status', 20)->default(Guest::ATTENDANCE_PENDING);
            $table->text('dietary_requirements')->nullable();
            $table->text('accessibility_requirements')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
