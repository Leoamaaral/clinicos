<?php

namespace App\Services;

use App\Models\ClientTreatmentPurchasePayment;
use App\Models\User;

class PurchaseDiscountService
{
    public const STAFF_MAX_CARD_DISCOUNT = 5.0;

    public const STAFF_MAX_CASH_PIX_DISCOUNT = 10.0;

    public const MIXED_PAYMENT_CASH_PIX_THRESHOLD = 50.0;

    /**
     * @param  array<int, array{method: string, amount: float|int|string}>  $payments
     */
    public function maxDiscountPercent(User $user, float $calculatedTotal, array $payments, float $discountPercent): float
    {
        if ($user->isAdmin()) {
            return 100.0;
        }

        return $this->maxDiscountForStaff($calculatedTotal, $payments, $discountPercent);
    }

    public function applyDiscount(float $calculatedTotal, float $discountPercent): float
    {
        return round($calculatedTotal * (1 - ($discountPercent / 100)), 2);
    }

    /**
     * @param  array<int, array{method: string, amount: float|int|string}>  $payments
     */
    public function paymentsTotal(array $payments): float
    {
        return round(collect($payments)->sum(fn (array $payment) => (float) $payment['amount']), 2);
    }

    /**
     * @param  array<int, array{method: string, amount: float|int|string}>  $payments
     */
    public function cashPixTotal(array $payments): float
    {
        return round(
            collect($payments)
                ->filter(fn (array $payment) => in_array($payment['method'], [
                    ClientTreatmentPurchasePayment::METHOD_CASH,
                    ClientTreatmentPurchasePayment::METHOD_PIX,
                ], true))
                ->sum(fn (array $payment) => (float) $payment['amount']),
            2,
        );
    }

    /**
     * @param  array<int, array{method: string, amount: float|int|string}>  $payments
     */
    private function maxDiscountForStaff(float $calculatedTotal, array $payments, float $discountPercent): float
    {
        if ($payments === []) {
            return self::STAFF_MAX_CASH_PIX_DISCOUNT;
        }

        $finalTotal = $this->applyDiscount($calculatedTotal, $discountPercent);
        $cashPixTotal = $this->cashPixTotal($payments);
        $hasCard = collect($payments)->contains(
            fn (array $payment) => $payment['method'] === ClientTreatmentPurchasePayment::METHOD_CARD,
        );
        $hasCashPix = $cashPixTotal > 0;

        if (! $hasCard && $hasCashPix) {
            return self::STAFF_MAX_CASH_PIX_DISCOUNT;
        }

        if ($hasCard && ! $hasCashPix) {
            return self::STAFF_MAX_CARD_DISCOUNT;
        }

        if ($finalTotal > 0 && (($cashPixTotal / $finalTotal) * 100) >= self::MIXED_PAYMENT_CASH_PIX_THRESHOLD) {
            return self::STAFF_MAX_CASH_PIX_DISCOUNT;
        }

        return self::STAFF_MAX_CARD_DISCOUNT;
    }
}
