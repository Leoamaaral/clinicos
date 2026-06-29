<?php

namespace App\Services;

use App\Models\ClientTreatmentPurchase;
use App\Models\Treatment;
use App\Models\TreatmentCombo;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class TreatmentPricingService
{
    /**
     * @param  array<int>  $treatmentIds
     * @return array{
     *     purchase_type: string,
     *     total_price: float,
     *     items: array<int, array{treatment_id: int, unit_price: float, sessions_total: int, combo_no_discount: bool}>
     * }
     */
    public function calculate(array $treatmentIds, string $billingMode, ?TreatmentCombo $combo = null): array
    {
        $treatments = Treatment::query()
            ->whereIn('id', $treatmentIds)
            ->where('is_active', true)
            ->get();

        if ($treatments->count() !== count(array_unique($treatmentIds))) {
            throw new InvalidArgumentException('Um ou mais tratamentos selecionados são inválidos.');
        }

        if (count($treatmentIds) < 1) {
            throw new InvalidArgumentException('Selecione ao menos um tratamento.');
        }

        if ($billingMode === 'single') {
            return $this->calculateSingle($treatments, false);
        }

        if (self::isPackageBillingMode($billingMode)) {
            $sessions = self::packageSessionsForBillingMode($billingMode);
            $combo ??= TreatmentCombo::activeForSessions($sessions);
            $comboMinCount = $combo?->min_treatment_count ?? 2;
            $isCombo = count($treatmentIds) >= $comboMinCount;

            return $isCombo
                ? $this->calculateCombo($treatments, $combo, $sessions)
                : $this->calculateSingle($treatments, true, $sessions);
        }

        throw new InvalidArgumentException('Modo de cobrança inválido.');
    }

    public static function isPackageBillingMode(string $billingMode): bool
    {
        return in_array($billingMode, ['package', 'package_6'], true);
    }

    public static function packageSessionsForBillingMode(string $billingMode): int
    {
        return match ($billingMode) {
            'package_6' => 6,
            'package' => 10,
            default => throw new InvalidArgumentException('Modo de cobrança inválido.'),
        };
    }

    public static function isComboPurchase(string $billingMode, int $treatmentCount, ?TreatmentCombo $combo = null): bool
    {
        if (! self::isPackageBillingMode($billingMode)) {
            return false;
        }

        $sessions = self::packageSessionsForBillingMode($billingMode);
        $combo ??= TreatmentCombo::activeForSessions($sessions);
        $comboMinCount = $combo?->min_treatment_count ?? 2;

        return $treatmentCount >= $comboMinCount;
    }

    /**
     * @param  Collection<int, Treatment>  $treatments
     * @return array{
     *     purchase_type: string,
     *     total_price: float,
     *     items: array<int, array{treatment_id: int, unit_price: float, sessions_total: int, combo_no_discount: bool}>
     * }
     */
    private function calculateSingle(Collection $treatments, bool $isPackage, int $sessions = 10): array
    {
        $items = [];
        $total = 0.0;

        foreach ($treatments as $treatment) {
            $price = (float) ($isPackage
                ? $treatment->packagePriceForSessions($sessions)
                : $treatment->single_price);
            $items[] = [
                'treatment_id' => $treatment->id,
                'unit_price' => $price,
                'sessions_total' => $isPackage ? $sessions : 1,
                'combo_no_discount' => false,
            ];
            $total += $price;
        }

        return [
            'purchase_type' => $isPackage
                ? self::packagePurchaseType($sessions)
                : ClientTreatmentPurchase::TYPE_SINGLE,
            'total_price' => round($total, 2),
            'items' => $items,
        ];
    }

    /**
     * @param  Collection<int, Treatment>  $treatments
     * @return array{
     *     purchase_type: string,
     *     total_price: float,
     *     items: array<int, array{treatment_id: int, unit_price: float, sessions_total: int, combo_no_discount: bool}>
     * }
     */
    private function calculateCombo(Collection $treatments, ?TreatmentCombo $combo, int $sessions): array
    {
        $combo ??= TreatmentCombo::activeForSessions($sessions);
        $discountPercent = (float) ($combo?->extra_discount_percent ?? 10);
        $discountFactor = 1 - ($discountPercent / 100);

        $sorted = $treatments
            ->sortByDesc(fn (Treatment $t) => $t->packagePriceForSessions($sessions))
            ->values();

        $items = [];
        $total = 0.0;

        foreach ($sorted as $index => $treatment) {
            $basePrice = $treatment->packagePriceForSessions($sessions);
            $noDiscount = $index === 0;
            $price = $noDiscount ? $basePrice : round($basePrice * $discountFactor, 2);

            $items[] = [
                'treatment_id' => $treatment->id,
                'unit_price' => $price,
                'sessions_total' => $sessions,
                'combo_no_discount' => $noDiscount,
            ];
            $total += $price;
        }

        return [
            'purchase_type' => self::comboPackagePurchaseType($sessions),
            'total_price' => round($total, 2),
            'items' => $items,
        ];
    }

    private static function packagePurchaseType(int $sessions): string
    {
        return match ($sessions) {
            6 => ClientTreatmentPurchase::TYPE_PACKAGE_6,
            10 => ClientTreatmentPurchase::TYPE_PACKAGE,
            default => throw new InvalidArgumentException("Pacote de {$sessions} sessões não suportado."),
        };
    }

    private static function comboPackagePurchaseType(int $sessions): string
    {
        return match ($sessions) {
            6 => ClientTreatmentPurchase::TYPE_COMBO_PACKAGE_6,
            10 => ClientTreatmentPurchase::TYPE_COMBO_PACKAGE,
            default => throw new InvalidArgumentException("Combo de {$sessions} sessões não suportado."),
        };
    }
}
