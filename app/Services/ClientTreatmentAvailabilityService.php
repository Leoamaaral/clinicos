<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentTreatment;
use App\Models\ClientTreatmentPurchaseItem;
use App\Models\Treatment;

class ClientTreatmentAvailabilityService
{
    /**
     * @param  array<int>  $alwaysIncludeTreatmentIds  Treatments always listed (e.g. current appointment).
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     single_price: string,
     *     duration_minutes: int,
     *     sessions_remaining: int
     * }>
     */
    public function getBookableTreatments(
        int $clientId,
        ?int $excludeAppointmentId = null,
        array $alwaysIncludeTreatmentIds = [],
    ): array {
        $items = ClientTreatmentPurchaseItem::query()
            ->whereHas('purchase', fn ($q) => $q->where('client_id', $clientId))
            ->whereHas('treatment', fn ($q) => $q->where('is_active', true))
            ->with('treatment:id,name,single_price,duration_minutes,is_active')
            ->get();

        $purchasedRemaining = [];
        $treatmentsById = [];

        foreach ($items as $item) {
            if (! $item->treatment) {
                continue;
            }

            $treatmentId = $item->treatment_id;
            $purchasedRemaining[$treatmentId] = ($purchasedRemaining[$treatmentId] ?? 0) + $item->sessionsRemaining();
            $treatmentsById[$treatmentId] = $item->treatment;
        }

        $pendingByTreatment = AppointmentTreatment::query()
            ->whereHas('appointment', function ($q) use ($clientId, $excludeAppointmentId) {
                $q->where('client_id', $clientId)
                    ->whereIn('status', [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED])
                    ->when($excludeAppointmentId, fn ($q) => $q->where('appointments.id', '!=', $excludeAppointmentId));
            })
            ->selectRaw('treatment_id, COUNT(*) as pending_count')
            ->groupBy('treatment_id')
            ->pluck('pending_count', 'treatment_id');

        $treatmentIds = collect(array_keys($purchasedRemaining))
            ->merge($alwaysIncludeTreatmentIds)
            ->unique()
            ->values();

        $missingIds = $treatmentIds
            ->diff(array_keys($treatmentsById))
            ->values()
            ->all();

        if ($missingIds !== []) {
            Treatment::query()
                ->whereIn('id', $missingIds)
                ->where('is_active', true)
                ->get(['id', 'name', 'single_price', 'duration_minutes'])
                ->each(fn (Treatment $treatment) => $treatmentsById[$treatment->id] = $treatment);
        }

        return $treatmentIds
            ->map(function (int $treatmentId) use ($purchasedRemaining, $pendingByTreatment, $treatmentsById, $alwaysIncludeTreatmentIds) {
                $treatment = $treatmentsById[$treatmentId] ?? null;

                if (! $treatment) {
                    return null;
                }

                $purchased = $purchasedRemaining[$treatmentId] ?? 0;
                $pending = (int) ($pendingByTreatment[$treatmentId] ?? 0);
                $sessionsRemaining = max(0, $purchased - $pending);

                if ($sessionsRemaining <= 0 && ! in_array($treatmentId, $alwaysIncludeTreatmentIds, true)) {
                    return null;
                }

                return [
                    'id' => $treatment->id,
                    'name' => $treatment->name,
                    'single_price' => (string) $treatment->single_price,
                    'duration_minutes' => (int) $treatment->duration_minutes,
                    'sessions_remaining' => $sessionsRemaining,
                ];
            })
            ->filter()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    public function canBook(int $clientId, int $treatmentId, ?int $excludeAppointmentId = null): bool
    {
        return collect($this->getBookableTreatments($clientId, $excludeAppointmentId))
            ->contains(fn (array $treatment) => $treatment['id'] === $treatmentId);
    }

    /**
     * @param  array<int>  $treatmentIds
     */
    public function canBookAll(int $clientId, array $treatmentIds, ?int $excludeAppointmentId = null): bool
    {
        foreach ($treatmentIds as $treatmentId) {
            if (! $this->canBook($clientId, (int) $treatmentId, $excludeAppointmentId)) {
                return false;
            }
        }

        return true;
    }
}
