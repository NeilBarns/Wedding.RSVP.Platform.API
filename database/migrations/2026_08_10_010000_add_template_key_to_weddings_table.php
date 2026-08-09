<?php

use App\Enums\WeddingTemplateKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weddings', function (Blueprint $table) {
            $table->string('template_key', 100)
                ->default(WeddingTemplateKey::EditorialLinenV1->value)
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('weddings', function (Blueprint $table) {
            $table->dropColumn('template_key');
        });
    }
};
