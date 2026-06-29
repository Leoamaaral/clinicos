<?php

namespace Tests\Unit;

use App\Models\ClientTreatmentPurchasePayment;
use App\Services\CardTerminalFeeService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CardTerminalFeeServiceTest extends TestCase
{
    private CardTerminalFeeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CardTerminalFeeService::class);
    }

    #[DataProvider('sellerInstallmentFeeProvider')]
    public function test_total_fee_percent_matches_maquinha_simulation(
        string $method,
        ?string $cardType,
        ?int $installments,
        float $expectedPercent,
    ): void {
        $this->assertSame(
            $expectedPercent,
            $this->service->totalFeePercent($method, $cardType, $installments),
        );
    }

  /**
     * @return array<string, array{0: string, 1: ?string, 2: ?int, 3: float}>
     */
    public static function sellerInstallmentFeeProvider(): array
    {
        return [
            'debit' => [
                ClientTreatmentPurchasePayment::METHOD_CARD,
                ClientTreatmentPurchasePayment::CARD_TYPE_DEBIT,
                1,
                0.99,
            ],
            'credit 1x' => [
                ClientTreatmentPurchasePayment::METHOD_CARD,
                ClientTreatmentPurchasePayment::CARD_TYPE_CREDIT,
                1,
                2.99,
            ],
            'credit 2x' => [
                ClientTreatmentPurchasePayment::METHOD_CARD,
                ClientTreatmentPurchasePayment::CARD_TYPE_CREDIT,
                2,
                4.09,
            ],
            'credit 6x' => [
                ClientTreatmentPurchasePayment::METHOD_CARD,
                ClientTreatmentPurchasePayment::CARD_TYPE_CREDIT,
                6,
                6.81,
            ],
            'credit 10x' => [
                ClientTreatmentPurchasePayment::METHOD_CARD,
                ClientTreatmentPurchasePayment::CARD_TYPE_CREDIT,
                10,
                9.63,
            ],
            'cash' => [
                ClientTreatmentPurchasePayment::METHOD_CASH,
                null,
                null,
                0.0,
            ],
            'pix' => [
                ClientTreatmentPurchasePayment::METHOD_PIX,
                null,
                null,
                0.0,
            ],
        ];
    }

    public function test_net_amount_for_hundred_real_credit_3x(): void
    {
        $this->assertSame(
            95.22,
            $this->service->netAmount(
                100.0,
                ClientTreatmentPurchasePayment::METHOD_CARD,
                ClientTreatmentPurchasePayment::CARD_TYPE_CREDIT,
                3,
            ),
        );
    }

    public function test_net_amount_for_hundred_real_debit(): void
    {
        $this->assertSame(
            99.01,
            $this->service->netAmount(
                100.0,
                ClientTreatmentPurchasePayment::METHOD_CARD,
                ClientTreatmentPurchasePayment::CARD_TYPE_DEBIT,
                1,
            ),
        );
    }
}
