<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->boolean('whatsapp_orientations_enabled')->default(true)->after('whatsapp_booking_message_template');
            $table->text('whatsapp_orientations_message_template')->nullable()->after('whatsapp_orientations_enabled');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->timestamp('whatsapp_orientations_sent_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_orientations_enabled', 'whatsapp_orientations_message_template']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('whatsapp_orientations_sent_at');
        });
    }
};
