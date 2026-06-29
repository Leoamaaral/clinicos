<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClinicSetting;
use App\Models\Treatment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppReminderResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_button_updates_appointment_status(): void
    {
        config([
            'services.whatsapp.api_url' => 'https://api.z-api.io/instances/test/token/secret/send-text',
            'services.whatsapp.client_token' => 'client-token-xyz',
        ]);

        Http::fake(['api.z-api.io/*' => Http::response(['messageId' => 'ok'], 200)]);

        $client = Client::create([
            'name' => 'Maria Silva',
            'phone' => '(41) 98801-7557',
            'cpf' => '123.456.789-00',
            'birth_date' => '1990-01-15',
        ]);
        $treatment = Treatment::create([
            'name' => 'Limpeza de pele',
            'single_price' => 150,
            'package_price' => 1200,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $appointment = Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => now()->addDay()->setTime(14, 0),
            'status' => Appointment::STATUS_SCHEDULED,
        ], [$treatment->id]);

        ClinicSetting::current()->update([
            'whatsapp_booking_message_template' => 'Confirmado, {nome}! Nos vemos em {data} às {hora} para {tratamento}.',
        ]);

        $response = $this->postJson(route('webhooks.whatsapp'), [
            'fromMe' => false,
            'phone' => '5541988017557',
            'buttonsResponseMessage' => [
                'buttonId' => "reminder_confirm_{$appointment->id}",
                'message' => 'Confirmar',
            ],
        ]);

        $response->assertOk()->assertJson(['received' => true]);

        $appointment->refresh();

        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);

        Http::assertSent(function ($request) use ($client) {
            $body = $request->data();

            return str_contains($request->url(), 'send-text')
                && str_contains($body['message'] ?? '', $client->name)
                && str_contains($body['message'] ?? '', 'Confirmado');
        });
    }

    public function test_reschedule_button_sends_follow_up_message(): void
    {
        config([
            'services.whatsapp.api_url' => 'https://api.z-api.io/instances/test/token/secret/send-text',
            'services.whatsapp.client_token' => 'client-token-xyz',
        ]);

        Http::fake(['api.z-api.io/*' => Http::response(['messageId' => 'ok'], 200)]);

        $client = Client::create([
            'name' => 'Maria Silva',
            'phone' => '(41) 98801-7557',
            'cpf' => '123.456.789-00',
            'birth_date' => '1990-01-15',
        ]);
        $treatment = Treatment::create([
            'name' => 'Limpeza de pele',
            'single_price' => 150,
            'package_price' => 1200,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $appointment = Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => now()->addDay()->setTime(14, 0),
            'status' => Appointment::STATUS_SCHEDULED,
        ], [$treatment->id]);

        $this->postJson(route('webhooks.whatsapp'), [
            'fromMe' => false,
            'phone' => '5541988017557',
            'buttonsResponseMessage' => [
                'buttonId' => "reminder_reschedule_{$appointment->id}",
                'message' => 'Reagendar',
            ],
        ])->assertOk();

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), 'send-text')
                && str_contains($body['message'] ?? '', 'reagendar');
        });
    }

    public function test_confirm_text_message_matches_phone_without_ninth_digit(): void
    {
        config([
            'services.whatsapp.api_url' => 'https://api.z-api.io/instances/test/token/secret/send-text',
            'services.whatsapp.client_token' => 'client-token-xyz',
        ]);

        Http::fake(['api.z-api.io/*' => Http::response(['messageId' => 'ok'], 200)]);

        $client = Client::create([
            'name' => 'Leonardo do Amaral',
            'phone' => '(41) 98801-7557',
            'cpf' => '109.526.349-88',
            'birth_date' => '1997-11-30',
        ]);
        $treatment = Treatment::create([
            'name' => 'Depilação a laser',
            'single_price' => 150,
            'package_price' => 1200,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $appointment = Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => now()->addDay()->setTime(11, 45),
            'status' => Appointment::STATUS_SCHEDULED,
            'whatsapp_reminder_sent_at' => now(),
        ], [$treatment->id]);

        $this->postJson(route('webhooks.whatsapp'), [
            'fromMe' => false,
            'phone' => '554188017557',
            'type' => 'ReceivedCallback',
            'text' => [
                'message' => 'Confirmar',
            ],
        ])->assertOk();

        $appointment->refresh();

        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
    }

    public function test_confirm_text_message_updates_appointment_status(): void
    {
        config([
            'services.whatsapp.api_url' => 'https://api.z-api.io/instances/test/token/secret/send-text',
            'services.whatsapp.client_token' => 'client-token-xyz',
        ]);

        Http::fake(['api.z-api.io/*' => Http::response(['messageId' => 'ok'], 200)]);

        $client = Client::create([
            'name' => 'Leonardo do Amaral',
            'phone' => '(41) 98801-7557',
            'cpf' => '123.456.789-01',
            'birth_date' => '1990-01-15',
        ]);
        $treatment = Treatment::create([
            'name' => 'Depilação a laser',
            'single_price' => 150,
            'package_price' => 1200,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $appointment = Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => now()->addDay()->setTime(11, 45),
            'status' => Appointment::STATUS_SCHEDULED,
            'whatsapp_reminder_sent_at' => now(),
        ], [$treatment->id]);

        $this->postJson(route('webhooks.whatsapp'), [
            'fromMe' => false,
            'phone' => '5541988017557',
            'type' => 'ReceivedCallback',
            'text' => [
                'message' => 'Confirmar',
            ],
        ])->assertOk();

        $appointment->refresh();

        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
    }

    public function test_webhook_ignores_messages_from_self(): void
    {
        Http::fake();

        $this->postJson(route('webhooks.whatsapp'), [
            'fromMe' => true,
            'phone' => '5541988017557',
            'buttonsResponseMessage' => [
                'buttonId' => 'reminder_confirm_1',
                'message' => 'Confirmar',
            ],
        ])->assertOk();

        Http::assertNothingSent();
    }
}
