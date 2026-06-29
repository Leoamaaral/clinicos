<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientTreatmentPurchase;
use App\Models\ClientTreatmentPurchaseItem;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTreatmentPurchaseDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_can_be_deleted_when_no_sessions_used(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'name' => 'Ana',
            'phone' => '41999990000',
            'cpf' => '111.111.111-11',
            'birth_date' => '1990-01-01',
        ]);

        $treatment = Treatment::create([
            'name' => 'Depilação axila',
            'single_price' => 100,
            'package_price' => 800,
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
            'treatment_id' => $treatment->id,
            'unit_price' => 100,
            'sessions_total' => 1,
            'sessions_used' => 0,
        ]);

        $this->actingAs($user)
            ->delete(route('clients.treatments.destroy', [$client, $purchase]))
            ->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseMissing('client_treatment_purchases', ['id' => $purchase->id]);
    }

    public function test_purchase_cannot_be_deleted_when_sessions_were_used(): void
    {
        $user = User::factory()->create();
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

        $this->actingAs($user)
            ->delete(route('clients.treatments.destroy', [$client, $purchase]))
            ->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseHas('client_treatment_purchases', ['id' => $purchase->id]);
    }

    public function test_purchase_cannot_be_deleted_with_active_appointments(): void
    {
        $user = User::factory()->create();
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

        Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => now()->addDay(),
            'status' => Appointment::STATUS_SCHEDULED,
        ], [$treatment->id]);

        $this->actingAs($user)
            ->delete(route('clients.treatments.destroy', [$client, $purchase]))
            ->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseHas('client_treatment_purchases', ['id' => $purchase->id]);
    }
}
