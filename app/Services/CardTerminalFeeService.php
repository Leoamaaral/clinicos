<?php

namespace App\Services;

use App\Models\ClientTreatmentPurchasePayment;

class CardTerminalFeeService
{
    public const ANTICIPATION_RATE = 1.43;

    private const FEE_DEBIT = 0.99;

    private const FEE_CREDIT_1X = 2.99;

    /**
     * Taxa total (MDR + antecipação) para parcelado vendedor — Visa/Master.
     *
     * @var array<int, float>
     */
    private const FEE_INSTALLMENTS = [
        2 => 4.09,
        3 => 4.78,
        4 => 5.47,
        5 => 6.14,
        6 => 6.81,
        7 => 7.67,
        8 => 8.33,
        9 => 8.98,
        10 => 9.63,
    ];

    public function totalFeePercent(string $method, ?string $cardType, ?int $installments): float
    {
        if ($method !== ClientTreatmentPurchasePayment::METHOD_CARD) {
            return 0.0;
        }

        if ($cardType === ClientTreatmentPurchasePayment::CARD_TYPE_DEBIT) {
            return self::FEE_DEBIT;
        }

        $installments ??= 1;

        if ($installments <= 1) {
            return self::FEE_CREDIT_1X;
        }

        return self::FEE_INSTALLMENTS[$installments] ?? self::FEE_CREDIT_1X;
    }

    public function feeAmount(float $amount, string $method, ?string $cardType, ?int $installments): float
    {
        $rate = $this->totalFeePercent($method, $cardType, $installments);

        return round($amount * ($rate / 100), 2);
    }

    public function netAmount(float $amount, string $method, ?string $cardType, ?int $installments): float
    {
        return round($amount - $this->feeAmount($amount, $method, $cardType, $installments), 2);
    }

    public function channelLabel(string $method, ?string $cardType, ?int $installments): string
    {
        return match ($method) {
            ClientTreatmentPurchasePayment::METHOD_CASH => 'Dinheiro',
            ClientTreatmentPurchasePayment::METHOD_PIX => 'Pix',
            ClientTreatmentPurchasePayment::METHOD_CARD => $this->cardLabel($cardType, $installments),
            default => $method,
        };
    }

    public function channelKey(string $method, ?string $cardType, ?int $installments): string
    {
        if ($method !== ClientTreatmentPurchasePayment::METHOD_CARD) {
            return $method;
        }

        if ($cardType === ClientTreatmentPurchasePayment::CARD_TYPE_DEBIT) {
            return 'card_debit';
        }

        $installments ??= 1;

        return $installments <= 1 ? 'card_credit_1x' : "card_credit_{$installments}x";
    }

    private function cardLabel(?string $cardType, ?int $installments): string
    {
        if ($cardType === ClientTreatmentPurchasePayment::CARD_TYPE_DEBIT) {
            return 'Cartão débito';
        }

        $installments ??= 1;

        if ($installments <= 1) {
            return 'Cartão crédito à vista';
        }

        return "Cartão crédito {$installments}x";
    }
}
