<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_settings', function (Blueprint $table) {
            $table->id();
            $table->string('clinic_name')->default(env('APP_NAME'));
            $table->string('clinic_phone')->nullable();
            $table->string('clinic_email')->nullable();
            $table->unsignedInteger('whatsapp_days_before')->default(1);
            $table->boolean('whatsapp_enabled')->default(true);
            $table->text('whatsapp_message_template')->nullable();
            $table->unsignedInteger('email_days_before')->default(1);
            $table->boolean('email_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('type');
            $table->string('status');
            $table->text('message')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('clinic_settings');
    }
};
