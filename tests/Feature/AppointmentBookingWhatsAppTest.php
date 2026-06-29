<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientTreatmentPurchase;
use App\Models\ClientTreatmentPurchaseItem;
use App\Models\ClinicSetting;
use App\Models\NotificationLog;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppointmentBookingWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private function assignTreatmentSessions(Client $client, Treatment $treatment): void
    {
        $purchase = ClientTreatmentPurchase::create([
            'client_id' => $client->id,
            'purchase_type' => ClientTreatmentPurchase::TYPE_PACKAGE,
            'total_price' => $treatment->package_price,
            'purchased_at' => now(),
        ]);

        ClientTreatmentPurchaseItem::create([
            'client_treatment_purchase_id' => $purchase->id,
            'treatment_id' => $treatment->id,
            'unit_price' => $treatment->package_price,
            'sessions_total' => 10,
            'sessions_used' => 0,
        ]);
    }

    public function test_sends_booking_whatsapp_when_appointment_is_created(): void
    {
        config([
            'services.whatsapp.api_url' => 'https://api.z-api.io/instances/test/token/secret/send-text',
            'services.whatsapp.client_token' => 'client-token',
        ]);

        Http::fake(['api.z-api.io/*' => Http::response(['messageId' => 'ok'], 200)]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = Client::create([
            'name' => 'Maria Silva',
            'phone' => '41988017557',
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
        $this->assignTreatmentSessions($client, $treatment);

        ClinicSetting::current()->update([
            'whatsapp_enabled' => true,
            'whatsapp_booking_enabled' => true,
            'whatsapp_booking_message_template' => 'Olá {nome}, agendamos {tratamento} para {data} às {hora}.',
            'whatsapp_orientations_enabled' => false,
        ]);

        $scheduledAt = now()->addDay()->setTime(14, 0);

        $response = $this->actingAs($admin)->post('/appointments', [
            'client_id' => $client->id,
            'treatment_ids' => [$treatment->id],
            'scheduled_at' => $scheduledAt->format('Y-m-d'),
            'scheduled_time' => '14:00',
            'scheduled_end_time' => '15:00',
            'status' => 'scheduled',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('notification_logs', [
            'client_id' => $client->id,
            'channel' => 'whatsapp',
            'type' => 'booking',
            'status' => 'sent',
        ]);

        Http::assertSent(function ($request) use ($client) {
            $body = $request->data();

            return str_contains($body['message'] ?? '', 'Olá')
                && str_contains($body['message'] ?? '', $client->name);
        });
    }

    public function test_sends_orientations_whatsapp_only_once_per_client(): void
    {
        config([
            'services.whatsapp.api_url' => 'https://api.z-api.io/instances/test/token/secret/send-text',
            'services.whatsapp.client_token' => 'client-token',
        ]);

        Http::fake(['api.z-api.io/*' => Http::response(['messageId' => 'ok'], 200)]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = Client::create([
            'name' => 'Ana Costa',
            'phone' => '41988011111',
            'cpf' => '111.222.333-44',
            'birth_date' => '1992-03-10',
        ]);
        $treatment = Treatment::create([
            'name' => 'Hidratação',
            'single_price' => 180,
            'package_price' => 1400,
            'duration_minutes' => 50,
            'is_active' => true,
        ]);
        $this->assignTreatmentSessions($client, $treatment);

        ClinicSetting::current()->update([
            'whatsapp_enabled' => true,
            'whatsapp_booking_enabled' => false,
            'whatsapp_orientations_enabled' => true,
            'whatsapp_orientations_message_template' => 'Olá {nome}, orientações importantes da {clinica}.',
        ]);

        $payload = [
            'client_id' => $client->id,
            'treatment_ids' => [$treatment->id],
            'scheduled_at' => now()->addDay()->format('Y-m-d'),
            'scheduled_time' => '10:00',
            'scheduled_end_time' => '10:50',
            'status' => 'scheduled',
        ];

        $this->actingAs($admin)->post('/appointments', $payload)->assertRedirect();

        $this->assertDatabaseHas('notification_logs', [
            'client_id' => $client->id,
            'type' => 'orientations',
            'status' => 'sent',
        ]);
        $this->assertNotNull($client->fresh()->whatsapp_orientations_sent_at);

        $this->actingAs($admin)->post('/appointments', [
            ...$payload,
            'scheduled_at' => now()->addDays(2)->format('Y-m-d'),
            'scheduled_time' => '11:00',
            'scheduled_end_time' => '11:50',
        ])->assertRedirect();

        $this->assertEquals(1, NotificationLog::where('client_id', $client->id)
            ->where('type', 'orientations')
            ->count());
    }

    public function test_skips_orientations_when_already_sent_to_client(): void
    {
        config([
            'services.whatsapp.api_url' => 'https://api.z-api.io/instances/test/token/secret/send-text',
            'services.whatsapp.client_token' => 'client-token',
        ]);

        Http::fake(['api.z-api.io/*' => Http::response(['messageId' => 'ok'], 200)]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = Client::create([
            'name' => 'Paula Lima',
            'phone' => '41988022222',
            'cpf' => '555.666.777-88',
            'birth_date' => '1988-07-25',
            'whatsapp_orientations_sent_at' => now()->subMonth(),
        ]);
        $treatment = Treatment::create([
            'name' => 'Drenagem',
            'single_price' => 120,
            'package_price' => 900,
            'duration_minutes' => 40,
            'is_active' => true,
        ]);
        $this->assignTreatmentSessions($client, $treatment);

        ClinicSetting::current()->update([
            'whatsapp_enabled' => true,
            'whatsapp_booking_enabled' => false,
            'whatsapp_orientations_enabled' => true,
            'whatsapp_orientations_message_template' => 'Orientações para {nome}.',
        ]);

        $this->actingAs($admin)->post('/appointments', [
            'client_id' => $client->id,
            'treatment_ids' => [$treatment->id],
            'scheduled_at' => now()->addDay()->format('Y-m-d'),
            'scheduled_time' => '09:00',
            'scheduled_end_time' => '09:40',
            'status' => 'scheduled',
        ]);

        Http::assertNothingSent();
        $this->assertDatabaseMissing('notification_logs', [
            'client_id' => $client->id,
            'type' => 'orientations',
        ]);
    }

    public function test_skips_booking_whatsapp_when_disabled(): void
    {
        Http::fake();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = Client::create([
            'name' => 'João Souza',
            'phone' => '41999998888',
            'cpf' => '987.654.321-00',
            'birth_date' => '1985-06-20',
        ]);
        $treatment = Treatment::create([
            'name' => 'Peeling',
            'single_price' => 200,
            'package_price' => 1600,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);
        $this->assignTreatmentSessions($client, $treatment);

        ClinicSetting::current()->update([
            'whatsapp_enabled' => false,
            'whatsapp_booking_enabled' => false,
            'whatsapp_orientations_enabled' => false,
            'whatsapp_booking_message_template' => 'Mensagem de teste',
        ]);

        $this->actingAs($admin)->post('/appointments', [
            'client_id' => $client->id,
            'treatment_ids' => [$treatment->id],
            'scheduled_at' => now()->addDay()->format('Y-m-d'),
            'scheduled_time' => '16:00',
            'scheduled_end_time' => '16:45',
            'status' => 'scheduled',
        ]);

        Http::assertNothingSent();
        $this->assertDatabaseMissing('notification_logs', ['type' => 'booking']);
    }
}
