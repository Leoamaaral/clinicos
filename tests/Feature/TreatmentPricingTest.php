<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Treatment;
use App\Models\TreatmentCombo;
use App\Models\User;
use App\Services\TreatmentPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_combo_applies_10_percent_from_second_treatment(): void
    {
        TreatmentCombo::create([
            'name' => 'Combo pacote 10 sessões',
            'sessions_count' => 10,
            'min_treatment_count' => 2,
            'extra_discount_percent' => 10,
            'is_active' => true,
        ]);

        $expensive = Treatment::create([
            'name' => 'Peeling',
            'single_price' => 300,
            'package_6_price' => 1500,
            'package_price' => 2500,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);
        $cheaper = Treatment::create([
            'name' => 'Laser axila',
            'single_price' => 100,
            'package_6_price' => 540,
            'package_price' => 900,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $service = new TreatmentPricingService;
        $result = $service->calculate(
            [$cheaper->id, $expensive->id],
            'package',
            TreatmentCombo::activeForSessions(10),
        );

        $this->assertSame('combo_package', $result['purchase_type']);
        $this->assertEqualsWithDelta(2500 + (900 * 0.9), $result['total_price'], 0.01);

        $primary = collect($result['items'])->firstWhere('combo_no_discount', true);
        $this->assertSame($expensive->id, $primary['treatment_id']);
    }

    public function test_single_mode_never_applies_combo_even_with_two_treatments(): void
    {
        TreatmentCombo::create([
            'name' => 'Combo pacote 10 sessões',
            'sessions_count' => 10,
            'min_treatment_count' => 2,
            'extra_discount_percent' => 10,
            'is_active' => true,
        ]);

        $a = Treatment::create([
            'name' => 'A',
            'single_price' => 200,
            'package_6_price' => 1080,
            'package_price' => 1800,
            'duration_minutes' => 60,
            'is_active' => true,
        ]);
        $b = Treatment::create([
            'name' => 'B',
            'single_price' => 100,
            'package_6_price' => 540,
            'package_price' => 900,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $service = new TreatmentPricingService;
        $result = $service->calculate([$a->id, $b->id], 'single', TreatmentCombo::activeForSessions(10));

        $this->assertSame('single', $result['purchase_type']);
        $this->assertEqualsWithDelta(300, $result['total_price'], 0.01);
    }

    public function test_package_combo_with_four_treatments_uses_10_percent(): void
    {
        TreatmentCombo::create([
            'name' => 'Combo pacote 10 sessões',
            'sessions_count' => 10,
            'min_treatment_count' => 2,
            'extra_discount_percent' => 10,
            'is_active' => true,
        ]);

        $treatments = collect([
            ['name' => 'A', 'package_price' => 2500],
            ['name' => 'B', 'package_price' => 1700],
            ['name' => 'C', 'package_price' => 900],
            ['name' => 'D', 'package_price' => 450],
        ])->map(fn (array $data) => Treatment::create([
            'name' => $data['name'],
            'single_price' => $data['package_price'] / 10,
            'package_6_price' => $data['package_price'] * 0.6,
            'package_price' => $data['package_price'],
            'duration_minutes' => 60,
            'is_active' => true,
        ]));

        $service = new TreatmentPricingService;
        $result = $service->calculate(
            $treatments->pluck('id')->all(),
            'package',
            TreatmentCombo::activeForSessions(10),
        );

        $this->assertSame('combo_package', $result['purchase_type']);
        $expected = 2500 + (1700 * 0.9) + (900 * 0.9) + (450 * 0.9);
        $this->assertEqualsWithDelta($expected, $result['total_price'], 0.01);
    }

    public function test_single_package_with_one_treatment_has_no_combo(): void
    {
        TreatmentCombo::create([
            'name' => 'Combo pacote 10 sessões',
            'sessions_count' => 10,
            'min_treatment_count' => 2,
            'extra_discount_percent' => 10,
            'is_active' => true,
        ]);

        $treatment = Treatment::create([
            'name' => 'Depilação pernas',
            'single_price' => 150,
            'package_6_price' => 720,
            'package_price' => 1200,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $service = new TreatmentPricingService;
        $result = $service->calculate([$treatment->id], 'package', TreatmentCombo::activeForSessions(10));

        $this->assertSame('package', $result['purchase_type']);
        $this->assertEqualsWithDelta(1200, $result['total_price'], 0.01);
    }

    public function test_staff_can_assign_treatments_to_client(): void
    {
        TreatmentCombo::create([
            'name' => 'Combo pacote 10 sessões',
            'sessions_count' => 10,
            'min_treatment_count' => 2,
            'extra_discount_percent' => 10,
            'is_active' => true,
        ]);

        $treatment = Treatment::create([
            'name' => 'Depilação pernas',
            'single_price' => 150,
            'package_6_price' => 720,
            'package_price' => 1200,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $client = Client::create([
            'name' => 'Ana Costa',
            'phone' => '41999998888',
            'cpf' => '111.222.333-44',
            'birth_date' => '1995-06-20',
        ]);

        $this->actingAs($user)
            ->post(route('clients.treatments.store', $client), [
                'billing_mode' => 'package',
                'treatment_ids' => [$treatment->id],
                'purchased_at' => '2026-05-29',
                'discount_percent' => 0,
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => 1200,
                    ],
                ],
            ])
            ->assertRedirect(route('clients.show', $client));

        $this->assertDatabaseHas('client_treatment_purchases', [
            'client_id' => $client->id,
            'purchase_type' => 'package',
            'total_price' => 1200,
        ]);
    }

    public function test_package_combo_with_six_sessions_applies_same_discount_rule(): void
    {
        TreatmentCombo::create([
            'name' => 'Combo pacote 6 sessões',
            'sessions_count' => 6,
            'min_treatment_count' => 2,
            'extra_discount_percent' => 10,
            'is_active' => true,
        ]);

        $expensive = Treatment::create([
            'name' => 'Peito',
            'single_price' => 350,
            'package_6_price' => 1740,
            'package_price' => 2900,
            'duration_minutes' => 45,
            'is_active' => true,
        ]);
        $cheaper = Treatment::create([
            'name' => 'Queixo',
            'single_price' => 100,
            'package_6_price' => 450,
            'package_price' => 750,
            'duration_minutes' => 15,
            'is_active' => true,
        ]);

        $service = new TreatmentPricingService;
        $result = $service->calculate(
            [$cheaper->id, $expensive->id],
            'package_6',
            TreatmentCombo::activeForSessions(6),
        );

        $this->assertSame('combo_package_6', $result['purchase_type']);
        $this->assertEqualsWithDelta(1740 + (450 * 0.9), $result['total_price'], 0.01);

        $primary = collect($result['items'])->firstWhere('combo_no_discount', true);
        $this->assertSame($expensive->id, $primary['treatment_id']);
        $this->assertSame(6, collect($result['items'])->first()['sessions_total']);
    }
}
