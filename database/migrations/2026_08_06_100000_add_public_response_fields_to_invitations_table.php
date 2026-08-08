<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->string('response_contact_number', 30)->nullable()->after('locked_at');
            $table->string('response_email', 254)->nullable()->after('response_contact_number');
            $table->text('message_to_couple')->nullable()->after('response_email');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn([
                'response_contact_number',
                'response_email',
                'message_to_couple',
            ]);
        });
    }
};
