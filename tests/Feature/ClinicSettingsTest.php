<?php

namespace Tests\Feature;

use App\Models\ClinicSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_whatsapp_message_templates_without_email_fields_in_form(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $settings = ClinicSetting::current();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'clinic_name' => 'Clínica Teste',
                'clinic_phone' => '41999990000',
                'clinic_email' => 'contato@clinica.test',
                'whatsapp_days_before' => 2,
                'whatsapp_enabled' => '1',
                'whatsapp_message_template' => 'Lembrete personalizado para {nome}.',
                'whatsapp_booking_message_template' => 'Confirmação personalizada para {nome}.',
            ])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHasNoErrors();

        $settings->refresh();

        $this->assertSame('Lembrete personalizado para {nome}.', $settings->whatsapp_message_template);
        $this->assertSame('Confirmação personalizada para {nome}.', $settings->whatsapp_booking_message_template);
        $this->assertSame(2, $settings->whatsapp_days_before);
    }

    public function test_admin_can_disable_whatsapp_booking_confirmation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $settings = ClinicSetting::current();
        $settings->update(['whatsapp_booking_enabled' => true]);

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'clinic_name' => $settings->clinic_name,
                'whatsapp_days_before' => $settings->whatsapp_days_before,
                'whatsapp_enabled' => '1',
                'whatsapp_booking_enabled' => '0',
                'whatsapp_message_template' => $settings->whatsapp_message_template,
                'whatsapp_booking_message_template' => $settings->whatsapp_booking_message_template,
            ])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHasNoErrors();

        $this->assertFalse($settings->fresh()->whatsapp_booking_enabled);
    }
}
