<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentTreatment;
use App\Models\Client;
use App\Models\ClientTreatmentPurchase;
use App\Models\ClientTreatmentPurchaseItem;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentSessionConsumptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_appointment_increments_purchased_sessions(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'name' => 'Leonardo',
            'phone' => '41999990000',
            'cpf' => '111.111.111-11',
            'birth_date' => '1990-01-01',
        ]);
        $treatment = Treatment::create([
            'name' => 'Limpeza',
            'single_price' => 100,
            'package_price' => 800,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $purchase = ClientTreatmentPurchase::create([
            'client_id' => $client->id,
            'purchase_type' => ClientTreatmentPurchase::TYPE_PACKAGE,
            'total_price' => 800,
            'purchased_at' => now(),
        ]);
        $item = ClientTreatmentPurchaseItem::create([
            'client_treatment_purchase_id' => $purchase->id,
            'treatment_id' => $treatment->id,
            'unit_price' => 800,
            'sessions_total' => 10,
            'sessions_used' => 0,
        ]);

        $scheduledAt = now()->addDay()->setTime(10, 0);
        $scheduledEndAt = $scheduledAt->copy()->addHour();

        $appointment = Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => $scheduledAt,
            'scheduled_end_at' => $scheduledEndAt,
            'status' => Appointment::STATUS_CONFIRMED,
        ], [$treatment->id]);

        $this->actingAs($user)
            ->put(route('appointments.update', $appointment), [
                'client_id' => $client->id,
                'treatment_ids' => [$treatment->id],
                'scheduled_at' => $scheduledAt->format('Y-m-d'),
                'scheduled_time' => '10:00',
                'scheduled_end_time' => '11:00',
                'status' => Appointment::STATUS_COMPLETED,
            ])
            ->assertRedirect();

        $item->refresh();
        $appointmentTreatment = AppointmentTreatment::query()
            ->where('appointment_id', $appointment->id)
            ->where('treatment_id', $treatment->id)
            ->first();

        $this->assertSame(1, $item->sessions_used);
        $this->assertSame($item->id, $appointmentTreatment->client_treatment_purchase_item_id);
    }

    public function test_reverting_completed_appointment_releases_session(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'name' => 'Ana',
            'phone' => '41988887777',
            'cpf' => '222.222.222-22',
            'birth_date' => '1995-05-05',
        ]);
        $treatment = Treatment::create([
            'name' => 'Laser',
            'single_price' => 150,
            'package_price' => 1200,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $purchase = ClientTreatmentPurchase::create([
            'client_id' => $client->id,
            'purchase_type' => ClientTreatmentPurchase::TYPE_PACKAGE,
            'total_price' => 1200,
            'purchased_at' => now(),
        ]);
        $item = ClientTreatmentPurchaseItem::create([
            'client_treatment_purchase_id' => $purchase->id,
            'treatment_id' => $treatment->id,
            'unit_price' => 1200,
            'sessions_total' => 10,
            'sessions_used' => 1,
        ]);

        $appointment = Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => now()->subHour(),
            'status' => Appointment::STATUS_COMPLETED,
        ], [$treatment->id]);

        AppointmentTreatment::query()
            ->where('appointment_id', $appointment->id)
            ->update(['client_treatment_purchase_item_id' => $item->id]);

        $this->actingAs($user)
            ->put(route('appointments.update', $appointment), [
                'client_id' => $client->id,
                'treatment_ids' => [$treatment->id],
                'scheduled_at' => $appointment->scheduled_at->format('Y-m-d H:i:s'),
                'status' => Appointment::STATUS_CANCELLED,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item->refresh();
        $appointmentTreatment = AppointmentTreatment::query()
            ->where('appointment_id', $appointment->id)
            ->where('treatment_id', $treatment->id)
            ->first();

        $this->assertSame(0, $item->sessions_used);
        $this->assertNull($appointmentTreatment->client_treatment_purchase_item_id);
    }
}
