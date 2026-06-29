<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientTreatmentPurchase;
use App\Models\ClientTreatmentPurchaseItem;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_treatment_can_be_deleted_when_no_purchases_exist(): void
    {
        $user = User::factory()->create();
        $treatment = Treatment::create([
            'name' => 'Limpeza',
            'single_price' => 100,
            'package_price' => 800,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->delete(route('treatments.destroy', $treatment))
            ->assertRedirect(route('treatments.index'));

        $this->assertDatabaseMissing('treatments', ['id' => $treatment->id]);
    }

    public function test_treatment_cannot_be_deleted_when_purchase_items_exist(): void
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

        ClientTreatmentPurchaseItem::create([
            'client_treatment_purchase_id' => $purchase->id,
            'treatment_id' => $treatment->id,
            'unit_price' => 800,
            'sessions_total' => 10,
            'sessions_used' => 0,
        ]);

        $this->actingAs($user)
            ->delete(route('treatments.destroy', $treatment))
            ->assertRedirect(route('treatments.index'));

        $this->assertDatabaseHas('treatments', ['id' => $treatment->id]);
    }
}
