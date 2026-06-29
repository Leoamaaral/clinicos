<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientTreatmentPurchase;
use App\Models\ClientTreatmentPurchasePayment;
use App\Models\Treatment;
use App\Models\User;
use App\Services\PurchaseDiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTreatmentPurchaseDiscountTest extends TestCase
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
     * @param  array<int, array{method: string, amount: float, installments?: int|null, card_type?: string|null}>  $payments
     * @return array<string, mixed>
     */
    private function storePayload(array $treatmentIds, array $payments, float $discountPercent = 0): array
    {
        return [
            'billing_mode' => 'package',
            'treatment_ids' => $treatmentIds,
            'purchased_at' => '2026-06-11',
            'discount_percent' => $discountPercent,
            'payments' => collect($payments)->map(function (array $payment) {
                $payload = [
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                ];

                if ($payment['method'] === ClientTreatmentPurchasePayment::METHOD_CARD) {
                    $payload['card_type'] = $payment['card_type']
                        ?? ClientTreatmentPurchasePayment::CARD_TYPE_CREDIT;
                    $payload['installments'] = $payment['installments'] ?? 1;
                }

                return $payload;
            })->all(),
        ];
    }

    public function test_admin_can_apply_unlimited_discount(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = $this->createClient();
        $treatment = $this->createTreatment();

        $this->actingAs($admin)
            ->post(route('clients.treatments.store', $client), $this->storePayload(
                [$treatment->id],
                [['method' => ClientTreatmentPurchasePayment::METHOD_CASH, 'amount' => 600]],
                50,
            ))
            ->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseHas('client_treatment_purchases', [
            'client_id' => $client->id,
            'calculated_price' => 1200,
            'discount_percent' => 50,
            'total_price' => 600,
        ]);
    }

    public function test_staff_is_limited_to_five_percent_on_card_only_payment(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $client = $this->createClient();
        $treatment = $this->createTreatment();

        $this->actingAs($staff)
            ->post(route('clients.treatments.store', $client), $this->storePayload(
                [$treatment->id],
                [['method' => ClientTreatmentPurchasePayment::METHOD_CARD, 'amount' => 1140, 'installments' => 3]],
                10,
            ))
            ->assertSessionHasErrors('discount_percent');

        $this->actingAs($staff)
            ->post(route('clients.treatments.store', $client), $this->storePayload(
                [$treatment->id],
                [['method' => ClientTreatmentPurchasePayment::METHOD_CARD, 'amount' => 1140, 'installments' => 3]],
                5,
            ))
            ->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseHas('client_treatment_purchases', [
            'client_id' => $client->id,
            'discount_percent' => 5,
            'total_price' => 1140,
        ]);
    }

    public function test_staff_can_apply_ten_percent_on_cash_or_pix(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $client = $this->createClient();
        $treatment = $this->createTreatment();

        $this->actingAs($staff)
            ->post(route('clients.treatments.store', $client), $this->storePayload(
                [$treatment->id],
                [['method' => ClientTreatmentPurchasePayment::METHOD_PIX, 'amount' => 1080]],
                10,
            ))
            ->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseHas('client_treatment_purchases', [
            'client_id' => $client->id,
            'discount_percent' => 10,
            'total_price' => 1080,
        ]);
    }

    public function test_staff_mixed_payment_allows_ten_percent_when_at_least_half_is_cash_or_pix(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $client = $this->createClient();
        $treatment = $this->createTreatment();

        $this->actingAs($staff)
            ->post(route('clients.treatments.store', $client), $this->storePayload(
                [$treatment->id],
                [
                    ['method' => ClientTreatmentPurchasePayment::METHOD_CASH, 'amount' => 540],
                    ['method' => ClientTreatmentPurchasePayment::METHOD_CARD, 'amount' => 540, 'installments' => 6],
                ],
                10,
            ))
            ->assertRedirect(route('clients.show', $client));

        $purchase = ClientTreatmentPurchase::query()->where('client_id', $client->id)->first();

        $this->assertNotNull($purchase);
        $this->assertEquals(10, (float) $purchase->discount_percent);
        $this->assertEquals(1080, (float) $purchase->total_price);
        $this->assertCount(2, $purchase->payments);
    }

    public function test_staff_mixed_payment_below_half_cash_or_pix_is_limited_to_five_percent(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $client = $this->createClient();
        $treatment = $this->createTreatment();

        $this->actingAs($staff)
            ->post(route('clients.treatments.store', $client), $this->storePayload(
                [$treatment->id],
                [
                    ['method' => ClientTreatmentPurchasePayment::METHOD_PIX, 'amount' => 400],
                    ['method' => ClientTreatmentPurchasePayment::METHOD_CARD, 'amount' => 740, 'installments' => 2],
                ],
                10,
            ))
            ->assertSessionHasErrors('discount_percent');
    }

    public function test_payments_must_match_discounted_total(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $client = $this->createClient();
        $treatment = $this->createTreatment();

        $this->actingAs($admin)
            ->post(route('clients.treatments.store', $client), $this->storePayload(
                [$treatment->id],
                [['method' => ClientTreatmentPurchasePayment::METHOD_CASH, 'amount' => 1000]],
                0,
            ))
            ->assertSessionHasErrors('payments');
    }

    public function test_purchase_discount_service_mixed_payment_threshold(): void
    {
        $service = new PurchaseDiscountService;

        $payments = [
            ['method' => ClientTreatmentPurchasePayment::METHOD_CASH, 'amount' => 540],
            ['method' => ClientTreatmentPurchasePayment::METHOD_CARD, 'amount' => 540],
        ];

        $maxDiscount = $service->maxDiscountPercent(
            User::factory()->make(['role' => User::ROLE_STAFF]),
            1200,
            $payments,
            10,
        );

        $this->assertSame(PurchaseDiscountService::STAFF_MAX_CASH_PIX_DISCOUNT, $maxDiscount);
    }
}
