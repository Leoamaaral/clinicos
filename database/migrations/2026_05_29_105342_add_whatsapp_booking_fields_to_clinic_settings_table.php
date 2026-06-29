<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->boolean('whatsapp_booking_enabled')->default(true)->after('whatsapp_message_template');
            $table->text('whatsapp_booking_message_template')->nullable()->after('whatsapp_booking_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_settings', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_booking_enabled', 'whatsapp_booking_message_template']);
        });
    }
};
