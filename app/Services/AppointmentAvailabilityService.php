<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Treatment;
use Carbon\Carbon;

class AppointmentAvailabilityService
{
    public const CLINIC_OPEN = '08:00';

    public const CLINIC_CLOSE = '22:00';

    /**
     * @param  array<int>  $treatmentIds
     * @return array{
     *     clinic_open: string,
     *     clinic_close: string,
     *     max_duration_minutes: int,
     *     busy_intervals: array<int, array{start: string, end: string}>
     * }
     */
    public function getAvailabilityContext(
        string $date,
        array $treatmentIds,
        ?int $professionalId = null,
        ?int $excludeAppointmentId = null,
    ): array {
        $day = Carbon::parse($date)->startOfDay();

        if ($day->lt(now()->startOfDay())) {
            return [
                'clinic_open' => self::CLINIC_OPEN,
                'clinic_close' => self::CLINIC_CLOSE,
                'max_duration_minutes' => $this->totalDurationForTreatments($treatmentIds),
                'busy_intervals' => [],
            ];
        }

        $busyIntervals = $this->getBusyIntervals($date, $professionalId, $excludeAppointmentId);

        return [
            'clinic_open' => self::CLINIC_OPEN,
            'clinic_close' => self::CLINIC_CLOSE,
            'max_duration_minutes' => $this->totalDurationForTreatments($treatmentIds),
            'busy_intervals' => array_map(
                fn (array $interval) => [
                    'start' => $interval['start']->format('H:i'),
                    'end' => $interval['end']->format('H:i'),
                ],
                $busyIntervals,
            ),
        ];
    }

    public function isIntervalAvailable(
        Carbon $scheduledAt,
        Carbon $scheduledEndAt,
        ?int $professionalId = null,
        ?int $excludeAppointmentId = null,
    ): bool {
        if (! $scheduledEndAt->gt($scheduledAt)) {
            return false;
        }

        $dayStart = $scheduledAt->copy()->setTimeFromTimeString(self::CLINIC_OPEN);
        $dayEnd = $scheduledAt->copy()->setTimeFromTimeString(self::CLINIC_CLOSE);

        if ($scheduledAt->lt(now()->startOfDay())) {
            return false;
        }

        if ($scheduledAt->lt($dayStart) || $scheduledEndAt->gt($dayEnd)) {
            return false;
        }

        if ($scheduledAt->lt(now())) {
            return false;
        }

        $busyIntervals = $this->getBusyIntervals(
            $scheduledAt->format('Y-m-d'),
            $professionalId,
            $excludeAppointmentId,
        );

        return ! $this->overlapsAny($scheduledAt, $scheduledEndAt, $busyIntervals);
    }

    /**
     * @param  array<int>  $treatmentIds
     */
    public function totalDurationForTreatments(array $treatmentIds): int
    {
        if ($treatmentIds === []) {
            return 0;
        }

        return (int) Treatment::query()
            ->whereIn('id', $treatmentIds)
            ->sum('duration_minutes');
    }

    /**
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    protected function getBusyIntervals(
        string $date,
        ?int $professionalId,
        ?int $excludeAppointmentId,
    ): array {
        $start = Carbon::parse($date)->startOfDay();
        $end = $start->copy()->endOfDay();

        $query = Appointment::query()
            ->with('treatments')
            ->whereBetween('scheduled_at', [$start, $end])
            ->whereNot('status', Appointment::STATUS_CANCELLED);

        if ($professionalId) {
            $query->where('user_id', $professionalId);
        }

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return $query->get()->map(function (Appointment $appointment) {
            return [
                'start' => $appointment->scheduled_at->copy(),
                'end' => $appointment->effectiveEndAt(),
            ];
        })->all();
    }

    /**
     * @param  array<int, array{start: Carbon, end: Carbon}>  $busyIntervals
     */
    protected function overlapsAny(Carbon $start, Carbon $end, array $busyIntervals): bool
    {
        foreach ($busyIntervals as $interval) {
            if ($start->lt($interval['end']) && $end->gt($interval['start'])) {
                return true;
            }
        }

        return false;
    }
}
