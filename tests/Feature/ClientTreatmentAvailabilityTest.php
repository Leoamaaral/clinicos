<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientTreatmentPurchase;
use App\Models\ClientTreatmentPurchaseItem;
use App\Models\Treatment;
use App\Models\User;
use App\Services\ClientTreatmentAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTreatmentAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_purchased_treatments_with_remaining_sessions(): void
    {
        $client = Client::create([
            'name' => 'Ana',
            'phone' => '41999990000',
            'cpf' => '111.111.111-11',
            'birth_date' => '1990-01-01',
        ]);

        $axila = Treatment::create([
            'name' => 'Depilação axila',
            'single_price' => 100,
            'package_price' => 800,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $peeling = Treatment::create([
            'name' => 'Peeling',
            'single_price' => 300,
            'package_price' => 2500,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $purchase = ClientTreatmentPurchase::create([
            'client_id' => $client->id,
            'purchase_type' => ClientTreatmentPurchase::TYPE_SINGLE,
            'total_price' => 100,
            'purchased_at' => now(),
        ]);

        ClientTreatmentPurchaseItem::create([
            'client_treatment_purchase_id' => $purchase->id,
            'treatment_id' => $axila->id,
            'unit_price' => 100,
            'sessions_total' => 1,
            'sessions_used' => 0,
        ]);

        $service = new ClientTreatmentAvailabilityService;
        $available = $service->getBookableTreatments($client->id);

        $this->assertCount(1, $available);
        $this->assertSame($axila->id, $available[0]['id']);
        $this->assertSame(1, $available[0]['sessions_remaining']);
        $this->assertFalse(collect($available)->contains('id', $peeling->id));
    }

    public function test_excludes_treatments_without_remaining_sessions(): void
    {
        $client = Client::create([
            'name' => 'João',
            'phone' => '41988887777',
            'cpf' => '222.222.222-22',
            'birth_date' => '1985-05-05',
        ]);

        $treatment = Treatment::create([
            'name' => 'Limpeza',
            'single_price' => 150,
            'package_price' => 1200,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $purchase = ClientTreatmentPurchase::create([
            'client_id' => $client->id,
            'purchase_type' => ClientTreatmentPurchase::TYPE_SINGLE,
            'total_price' => 150,
            'purchased_at' => now(),
        ]);

        ClientTreatmentPurchaseItem::create([
            'client_treatment_purchase_id' => $purchase->id,
            'treatment_id' => $treatment->id,
            'unit_price' => 150,
            'sessions_total' => 1,
            'sessions_used' => 1,
        ]);

        $service = new ClientTreatmentAvailabilityService;

        $this->assertEmpty($service->getBookableTreatments($client->id));
        $this->assertFalse($service->canBook($client->id, $treatment->id));
    }

    public function test_subtracts_pending_appointments_from_remaining_sessions(): void
    {
        $client = Client::create([
            'name' => 'Maria',
            'phone' => '41977776666',
            'cpf' => '333.333.333-33',
            'birth_date' => '1992-03-10',
        ]);

        $treatment = Treatment::create([
            'name' => 'Laser',
            'single_price' => 200,
            'package_price' => 1800,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $purchase = ClientTreatmentPurchase::create([
            'client_id' => $client->id,
            'purchase_type' => ClientTreatmentPurchase::TYPE_SINGLE,
            'total_price' => 200,
            'purchased_at' => now(),
        ]);

        ClientTreatmentPurchaseItem::create([
            'client_treatment_purchase_id' => $purchase->id,
            'treatment_id' => $treatment->id,
            'unit_price' => 200,
            'sessions_total' => 1,
            'sessions_used' => 0,
        ]);

        $appointment = Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => now()->addDay(),
            'status' => Appointment::STATUS_SCHEDULED,
        ], [$treatment->id]);

        $service = new ClientTreatmentAvailabilityService;

        $this->assertEmpty($service->getBookableTreatments($client->id));
        $this->assertCount(1, $service->getBookableTreatments($client->id, $appointment->id));
    }

    public function test_available_treatments_endpoint_requires_auth(): void
    {
        $client = Client::create([
            'name' => 'Teste',
            'phone' => '41966665555',
            'cpf' => '444.444.444-44',
            'birth_date' => '1988-08-08',
        ]);

        $this->getJson(route('clients.available-treatments', $client))
            ->assertUnauthorized();
    }

    public function test_available_treatments_endpoint_returns_json(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'name' => 'Carla',
            'phone' => '41955554444',
            'cpf' => '555.555.555-55',
            'birth_date' => '1991-11-11',
        ]);

        $treatment = Treatment::create([
            'name' => 'Botox',
            'single_price' => 500,
            'package_price' => 4000,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $purchase = ClientTreatmentPurchase::create([
            'client_id' => $client->id,
            'purchase_type' => ClientTreatmentPurchase::TYPE_SINGLE,
            'total_price' => 500,
            'purchased_at' => now(),
        ]);

        ClientTreatmentPurchaseItem::create([
            'client_treatment_purchase_id' => $purchase->id,
            'treatment_id' => $treatment->id,
            'unit_price' => 500,
            'sessions_total' => 2,
            'sessions_used' => 0,
        ]);

        $this->actingAs($user)
            ->getJson(route('clients.available-treatments', $client))
            ->assertOk()
            ->assertJsonPath('treatments.0.id', $treatment->id)
            ->assertJsonPath('treatments.0.sessions_remaining', 2);
    }
}
