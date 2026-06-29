<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClientTreatmentPurchaseItem;
use Illuminate\Support\Facades\DB;

class AppointmentSessionService
{
    public function syncAfterUpdate(Appointment $appointment, string $previousStatus): void
    {
        $wasCompleted = $previousStatus === Appointment::STATUS_COMPLETED;
        $isCompleted = $appointment->status === Appointment::STATUS_COMPLETED;

        if ($wasCompleted && ! $isCompleted) {
            $this->release($appointment);

            return;
        }

        if (! $wasCompleted && $isCompleted) {
            $this->consume($appointment);

            return;
        }

        if ($wasCompleted && $isCompleted && $appointment->wasChanged(['client_id'])) {
            $this->release($appointment);
            $this->consume($appointment);
        }
    }

    public function syncAfterCreate(Appointment $appointment): void
    {
        if ($appointment->status === Appointment::STATUS_COMPLETED) {
            $this->consume($appointment);
        }
    }

    public function syncAfterTreatmentChange(Appointment $appointment): void
    {
        if ($appointment->status !== Appointment::STATUS_COMPLETED) {
            return;
        }

        $this->release($appointment);
        $this->consume($appointment);
    }

    public function releaseBeforeDelete(Appointment $appointment): void
    {
        if ($appointment->status === Appointment::STATUS_COMPLETED) {
            $this->release($appointment);
        }
    }

    private function consume(Appointment $appointment): void
    {
        $appointment->loadMissing('appointmentTreatments');

        DB::transaction(function () use ($appointment) {
            foreach ($appointment->appointmentTreatments as $appointmentTreatment) {
                if ($appointmentTreatment->client_treatment_purchase_item_id) {
                    continue;
                }

                $item = $this->findAvailableItem(
                    $appointment->client_id,
                    $appointmentTreatment->treatment_id,
                );

                if (! $item) {
                    continue;
                }

                $item->increment('sessions_used');
                $appointmentTreatment->updateQuietly([
                    'client_treatment_purchase_item_id' => $item->id,
                ]);
            }
        });
    }

    private function release(Appointment $appointment): void
    {
        $appointment->loadMissing('appointmentTreatments');

        DB::transaction(function () use ($appointment) {
            foreach ($appointment->appointmentTreatments as $appointmentTreatment) {
                $itemId = $appointmentTreatment->client_treatment_purchase_item_id;

                if (! $itemId) {
                    continue;
                }

                $item = ClientTreatmentPurchaseItem::query()->lockForUpdate()->find($itemId);

                if ($item && $item->sessions_used > 0) {
                    $item->decrement('sessions_used');
                }

                $appointmentTreatment->updateQuietly([
                    'client_treatment_purchase_item_id' => null,
                ]);
            }
        });
    }

    private function findAvailableItem(int $clientId, int $treatmentId): ?ClientTreatmentPurchaseItem
    {
        return ClientTreatmentPurchaseItem::query()
            ->where('treatment_id', $treatmentId)
            ->whereHas('purchase', fn ($q) => $q->where('client_id', $clientId))
            ->whereColumn('sessions_used', '<', 'sessions_total')
            ->orderBy('id')
            ->first();
    }
}
