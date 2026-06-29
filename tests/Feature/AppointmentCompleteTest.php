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

class AppointmentCompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_index_renders_appointments_for_the_day(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'name' => 'Maria',
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

        $date = now()->format('Y-m-d');

        Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => now()->setTime(10, 0),
            'status' => Appointment::STATUS_CONFIRMED,
        ], [$treatment->id]);

        Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => now()->setTime(14, 0),
            'status' => Appointment::STATUS_SCHEDULED,
        ], [$treatment->id]);

        Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => now()->addDay()->setTime(10, 0),
            'status' => Appointment::STATUS_CONFIRMED,
        ], [$treatment->id]);

        $this->actingAs($user)
            ->get(route('appointments.complete.index', ['date' => $date]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('appointments/complete')
                ->has('appointments', 2)
                ->where('counts.total', 2)
                ->where('counts.pending', 2)
                ->where('counts.completed', 0));
    }

    public function test_complete_index_filters_by_professional(): void
    {
        $user = User::factory()->create();
        $professionalA = User::factory()->create(['name' => 'Ana']);
        $professionalB = User::factory()->create(['name' => 'Bruno']);
        $client = Client::create([
            'name' => 'Cliente',
            'phone' => '41999990000',
            'cpf' => '222.222.222-22',
            'birth_date' => '1990-01-01',
        ]);
        $treatment = Treatment::create([
            'name' => 'Peeling',
            'single_price' => 100,
            'package_price' => 800,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $date = now()->format('Y-m-d');

        Appointment::createWithTreatments([
            'client_id' => $client->id,
            'user_id' => $professionalA->id,
            'scheduled_at' => now()->setTime(9, 0),
            'status' => Appointment::STATUS_CONFIRMED,
        ], [$treatment->id]);

        Appointment::createWithTreatments([
            'client_id' => $client->id,
            'user_id' => $professionalB->id,
            'scheduled_at' => now()->setTime(11, 0),
            'status' => Appointment::STATUS_CONFIRMED,
        ], [$treatment->id]);

        $this->actingAs($user)
            ->get(route('appointments.complete.index', [
                'date' => $date,
                'professional_id' => $professionalA->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('appointments', 1)
                ->where('appointments.0.user_id', $professionalA->id));
    }

    public function test_complete_marks_appointment_and_consumes_session(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'name' => 'Leonardo',
            'phone' => '41999990000',
            'cpf' => '333.333.333-33',
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

        $appointment = Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => now()->setTime(10, 0),
            'status' => Appointment::STATUS_CONFIRMED,
        ], [$treatment->id]);

        $this->actingAs($user)
            ->from(route('appointments.complete.index'))
            ->patch(route('appointments.complete', $appointment))
            ->assertRedirect();

        $appointment->refresh();
        $item->refresh();
        $appointmentTreatment = AppointmentTreatment::query()
            ->where('appointment_id', $appointment->id)
            ->where('treatment_id', $treatment->id)
            ->first();

        $this->assertSame(Appointment::STATUS_COMPLETED, $appointment->status);
        $this->assertSame(1, $item->sessions_used);
        $this->assertSame($item->id, $appointmentTreatment->client_treatment_purchase_item_id);
    }

    public function test_uncomplete_reverts_and_releases_session(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'name' => 'Ana',
            'phone' => '41988887777',
            'cpf' => '444.444.444-44',
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
            'scheduled_at' => now()->setTime(10, 0),
            'status' => Appointment::STATUS_COMPLETED,
        ], [$treatment->id]);

        AppointmentTreatment::query()
            ->where('appointment_id', $appointment->id)
            ->update(['client_treatment_purchase_item_id' => $item->id]);

        $this->actingAs($user)
            ->from(route('appointments.complete.index'))
            ->patch(route('appointments.uncomplete', $appointment))
            ->assertRedirect();

        $appointment->refresh();
        $item->refresh();
        $appointmentTreatment = AppointmentTreatment::query()
            ->where('appointment_id', $appointment->id)
            ->where('treatment_id', $treatment->id)
            ->first();

        $this->assertSame(Appointment::STATUS_CONFIRMED, $appointment->status);
        $this->assertSame(0, $item->sessions_used);
        $this->assertNull($appointmentTreatment->client_treatment_purchase_item_id);
    }

    public function test_complete_bulk_completes_all_pending_for_the_day(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'name' => 'Cliente',
            'phone' => '41977776666',
            'cpf' => '555.555.555-55',
            'birth_date' => '1990-01-01',
        ]);
        $treatment = Treatment::create([
            'name' => 'Massagem',
            'single_price' => 80,
            'package_price' => 600,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $date = now()->format('Y-m-d');

        $pendingOne = Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => now()->setTime(9, 0),
            'status' => Appointment::STATUS_SCHEDULED,
        ], [$treatment->id]);

        $pendingTwo = Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => now()->setTime(10, 0),
            'status' => Appointment::STATUS_CONFIRMED,
        ], [$treatment->id]);

        $alreadyCompleted = Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => now()->setTime(11, 0),
            'status' => Appointment::STATUS_COMPLETED,
        ], [$treatment->id]);

        $this->actingAs($user)
            ->post(route('appointments.complete.bulk'), ['date' => $date])
            ->assertRedirect(route('appointments.complete.index', ['date' => $date]));

        $this->assertSame(Appointment::STATUS_COMPLETED, $pendingOne->fresh()->status);
        $this->assertSame(Appointment::STATUS_COMPLETED, $pendingTwo->fresh()->status);
        $this->assertSame(Appointment::STATUS_COMPLETED, $alreadyCompleted->fresh()->status);
    }

    public function test_complete_bulk_respects_professional_filter(): void
    {
        $user = User::factory()->create();
        $professionalA = User::factory()->create(['name' => 'Ana']);
        $professionalB = User::factory()->create(['name' => 'Bruno']);
        $client = Client::create([
            'name' => 'Cliente',
            'phone' => '41966665555',
            'cpf' => '666.666.666-66',
            'birth_date' => '1990-01-01',
        ]);
        $treatment = Treatment::create([
            'name' => 'Drenagem',
            'single_price' => 90,
            'package_price' => 700,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $date = now()->format('Y-m-d');

        $forA = Appointment::createWithTreatments([
            'client_id' => $client->id,
            'user_id' => $professionalA->id,
            'scheduled_at' => now()->setTime(9, 0),
            'status' => Appointment::STATUS_CONFIRMED,
        ], [$treatment->id]);

        $forB = Appointment::createWithTreatments([
            'client_id' => $client->id,
            'user_id' => $professionalB->id,
            'scheduled_at' => now()->setTime(10, 0),
            'status' => Appointment::STATUS_CONFIRMED,
        ], [$treatment->id]);

        $this->actingAs($user)
            ->post(route('appointments.complete.bulk'), [
                'date' => $date,
                'professional_id' => $professionalA->id,
            ])
            ->assertRedirect(route('appointments.complete.index', [
                'date' => $date,
                'professional_id' => $professionalA->id,
            ]));

        $this->assertSame(Appointment::STATUS_COMPLETED, $forA->fresh()->status);
        $this->assertSame(Appointment::STATUS_CONFIRMED, $forB->fresh()->status);
    }

    public function test_complete_rejects_cancelled_appointment(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'name' => 'Cliente',
            'phone' => '41955554444',
            'cpf' => '777.777.777-77',
            'birth_date' => '1990-01-01',
        ]);
        $treatment = Treatment::create([
            'name' => 'Consulta',
            'single_price' => 50,
            'package_price' => 400,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $appointment = Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => now()->setTime(10, 0),
            'status' => Appointment::STATUS_CANCELLED,
        ], [$treatment->id]);

        $this->actingAs($user)
            ->from(route('appointments.complete.index'))
            ->patch(route('appointments.complete', $appointment))
            ->assertRedirect();

        $this->assertSame(Appointment::STATUS_CANCELLED, $appointment->fresh()->status);
    }

    public function test_complete_is_idempotent(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'name' => 'Cliente',
            'phone' => '41944443333',
            'cpf' => '888.888.888-88',
            'birth_date' => '1990-01-01',
        ]);
        $treatment = Treatment::create([
            'name' => 'Botox',
            'single_price' => 200,
            'package_price' => 1600,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $purchase = ClientTreatmentPurchase::create([
            'client_id' => $client->id,
            'purchase_type' => ClientTreatmentPurchase::TYPE_PACKAGE,
            'total_price' => 1600,
            'purchased_at' => now(),
        ]);
        $item = ClientTreatmentPurchaseItem::create([
            'client_treatment_purchase_id' => $purchase->id,
            'treatment_id' => $treatment->id,
            'unit_price' => 1600,
            'sessions_total' => 10,
            'sessions_used' => 1,
        ]);

        $appointment = Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => now()->setTime(10, 0),
            'status' => Appointment::STATUS_COMPLETED,
        ], [$treatment->id]);

        AppointmentTreatment::query()
            ->where('appointment_id', $appointment->id)
            ->update(['client_treatment_purchase_item_id' => $item->id]);

        $this->actingAs($user)
            ->from(route('appointments.complete.index'))
            ->patch(route('appointments.complete', $appointment))
            ->assertRedirect();

        $item->refresh();
        $this->assertSame(1, $item->sessions_used);
    }
}
