import { useEffect, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    addMinutesToTime,
    diffMinutesBetweenTimes,
    formatDuration,
    timeRangeOverlapsBusyIntervals,
    timeToMinutes,
} from '@/lib/clinic';

type BusyInterval = {
    start: string;
    end: string;
};

type AvailabilityResponse = {
    clinic_open: string;
    clinic_close: string;
    max_duration_minutes: number;
    busy_intervals: BusyInterval[];
};

type TimeSlotPickerProps = {
    date: string;
    treatmentIds: number[];
    professionalId: string;
    startTime: string;
    endTime: string;
    onStartChange: (time: string) => void;
    onEndChange: (time: string) => void;
    excludeAppointmentId?: number;
    error?: string;
    endError?: string;
};

function validateTimeRange(
    startTime: string,
    endTime: string,
    clinicOpen: string,
    clinicClose: string,
    busyIntervals: BusyInterval[],
): string | null {
    if (!startTime || !endTime) {
        return null;
    }

    const startMinutes = timeToMinutes(startTime);
    const endMinutes = timeToMinutes(endTime);
    const openMinutes = timeToMinutes(clinicOpen);
    const closeMinutes = timeToMinutes(clinicClose);

    if (endMinutes <= startMinutes) {
        return 'O horário de término deve ser posterior ao início.';
    }

    if (startMinutes < openMinutes || endMinutes > closeMinutes) {
        return `O atendimento deve estar entre ${clinicOpen} e ${clinicClose}.`;
    }

    if (timeRangeOverlapsBusyIntervals(startTime, endTime, busyIntervals)) {
        return 'Este horário conflita com outro agendamento.';
    }

    return null;
}

export function TimeSlotPicker({
    date,
    treatmentIds,
    professionalId,
    startTime,
    endTime,
    onStartChange,
    onEndChange,
    excludeAppointmentId,
    error,
    endError,
}: TimeSlotPickerProps) {
    const [availability, setAvailability] = useState<AvailabilityResponse | null>(null);
    const [loading, setLoading] = useState(false);

    const treatmentKey = treatmentIds.join(',');

    useEffect(() => {
        if (!date || treatmentIds.length === 0) {
            setAvailability(null);

            return;
        }

        const controller = new AbortController();
        setLoading(true);

        const params = new URLSearchParams({ date });
        treatmentIds.forEach((id) => params.append('treatment_ids[]', String(id)));

        if (professionalId) {
            params.set('user_id', professionalId);
        }

        if (excludeAppointmentId) {
            params.set('exclude_appointment_id', String(excludeAppointmentId));
        }

        fetch(`/appointments/available-slots?${params}`, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((res) => res.json())
            .then((data: AvailabilityResponse) => {
                setAvailability(data);
            })
            .catch(() => {
                if (!controller.signal.aborted) {
                    setAvailability(null);
                }
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [date, treatmentKey, professionalId, excludeAppointmentId]);

    const clientValidationError = useMemo(() => {
        if (!availability || !startTime || !endTime) {
            return null;
        }

        return validateTimeRange(
            startTime,
            endTime,
            availability.clinic_open,
            availability.clinic_close,
            availability.busy_intervals,
        );
    }, [availability, startTime, endTime]);

    const selectedDuration = useMemo(() => {
        if (!startTime || !endTime) {
            return null;
        }

        const minutes = diffMinutesBetweenTimes(startTime, endTime);

        return minutes > 0 ? minutes : null;
    }, [startTime, endTime]);

    const handleStartChange = (time: string) => {
        onStartChange(time);

        if (time && availability && availability.max_duration_minutes > 0) {
            onEndChange(addMinutesToTime(date, time, availability.max_duration_minutes));
        }
    };

    if (treatmentIds.length === 0) {
        return (
            <p className="text-muted-foreground text-sm">
                Selecione ao menos um tratamento para ver os horários disponíveis.
            </p>
        );
    }

    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between">
                <Label>Horário disponível *</Label>
                {availability && availability.max_duration_minutes > 0 && (
                    <span className="text-muted-foreground text-xs">
                        Duração dos tratamentos: {formatDuration(availability.max_duration_minutes)}
                    </span>
                )}
            </div>

            {loading ? (
                <p className="text-muted-foreground text-sm">Carregando horários...</p>
            ) : (
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="scheduled_time">Início</Label>
                        <Input
                            id="scheduled_time"
                            name="scheduled_time"
                            type="time"
                            min={availability?.clinic_open}
                            max={availability?.clinic_close}
                            value={startTime}
                            onChange={(e) => handleStartChange(e.target.value)}
                            required
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="scheduled_end_time">Término</Label>
                        <Input
                            id="scheduled_end_time"
                            name="scheduled_end_time"
                            type="time"
                            min={availability?.clinic_open}
                            max={availability?.clinic_close}
                            value={endTime}
                            onChange={(e) => onEndChange(e.target.value)}
                            required
                        />
                    </div>
                </div>
            )}

            {selectedDuration !== null && (
                <p className="text-muted-foreground text-xs">
                    Atendimento: {formatDuration(selectedDuration)}
                </p>
            )}

            {clientValidationError && (
                <p className="text-destructive text-sm">{clientValidationError}</p>
            )}

            <InputError message={error} />
            <InputError message={endError} />
        </div>
    );
}
