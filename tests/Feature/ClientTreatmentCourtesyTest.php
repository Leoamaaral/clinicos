<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientTreatmentPurchase;
use App\Models\ClientTreatmentPurchaseItem;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTreatmentCourtesyTest extends TestCase
{
    use RefreshDatabase;

    private function createTreatment(): Treatment
    {
        return Treatment::create([
            'name' => 'Depilação pernas',
            'single_price' => 150,
            'package_6_price' => 720,
            'package_price' => 1200,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);
    }

    private function createClient(): Client
    {
        return Client::create([
            'name' => 'Ana Costa',
            'phone' => '41999998888',
            'cpf' => '111.222.333-44',
            'birth_date' => '1995-06-20',
        ]);
    }

    /**
     * @param  array<int>  $treatmentIds
     * @return array<string, mixed>
     */
    private function courtesyPayload(array $treatmentIds, string $billingMode = 'single'): array
    {
        return [
            'billing_mode' => $billingMode,
            'treatment_ids' => $treatmentIds,
            'purchased_at' => '2026-06-12',
            'is_courtesy' => true,
            'payments' => [],
        ];
    }

    public function test_staff_can_register_courtesy_single_session(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $client = $this->createClient();
        $treatment = $this->createTreatment();

        $this->actingAs($staff)
            ->post(route('clients.treatments.store', $client), $this->courtesyPayload([$treatment->id]))
            ->assertRedirect(route('clients.show', $client));

        $purchase = ClientTreatmentPurchase::query()->where('client_id', $client->id)->first();

        $this->assertNotNull($purchase);
        $this->assertTrue($purchase->is_courtesy);
        $this->assertEquals(150, (float) $purchase->calculated_price);
        $this->assertEquals(0, (float) $purchase->total_price);
        $this->assertEquals(0, (float) $purchase->discount_percent);
        $this->assertCount(0, $purchase->payments);
        $this->assertCount(1, $purchase->items);
        $this->assertEquals(1, $purchase->items->first()->sessions_total);
    }

    public function test_admin_can_register_courtesy_package(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = $this->createClient();
        $treatment = $this->createTreatment();

        $this->actingAs($admin)
            ->post(
                route('clients.treatments.store', $client),
                $this->courtesyPayload([$treatment->id], 'package'),
            )
            ->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseHas('client_treatment_purchases', [
            'client_id' => $client->id,
            'is_courtesy' => true,
            'calculated_price' => 1200,
            'total_price' => 0,
        ]);

        $item = ClientTreatmentPurchaseItem::query()->first();
        $this->assertNotNull($item);
        $this->assertEquals(10, $item->sessions_total);
    }

    public function test_paid_purchase_still_requires_payments(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = $this->createClient();
        $treatment = $this->createTreatment();

        $this->actingAs($admin)
            ->post(route('clients.treatments.store', $client), [
                'billing_mode' => 'package',
                'treatment_ids' => [$treatment->id],
                'purchased_at' => '2026-06-12',
                'is_courtesy' => false,
                'discount_percent' => 0,
                'payments' => [],
            ])
            ->assertSessionHasErrors('payments');
    }

    public function test_courtesy_treatments_are_available_for_booking(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $client = $this->createClient();
        $treatment = $this->createTreatment();

        $this->actingAs($staff)
            ->post(route('clients.treatments.store', $client), $this->courtesyPayload([$treatment->id]))
            ->assertRedirect(route('clients.show', $client));

        $this->actingAs($staff)
            ->getJson(route('clients.available-treatments', $client))
            ->assertOk()
            ->assertJsonPath('treatments.0.id', $treatment->id)
            ->assertJsonPath('treatments.0.sessions_remaining', 1);
    }
}
