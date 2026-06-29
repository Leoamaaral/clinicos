import { useCallback, useEffect, useState } from 'react';

export type ClientAvailableTreatment = {
    id: number;
    name: string;
    single_price: string;
    duration_minutes: number;
    sessions_remaining: number;
};

type Options = {
    clientId: string;
    excludeAppointmentId?: number;
    includeTreatmentIds?: number[];
};

export function useClientAvailableTreatments({
    clientId,
    excludeAppointmentId,
    includeTreatmentIds = [],
}: Options) {
    const [treatments, setTreatments] = useState<ClientAvailableTreatment[]>([]);
    const [loading, setLoading] = useState(false);

    const includeKey = includeTreatmentIds.join(',');

    const fetchTreatments = useCallback(async () => {
        if (!clientId) {
            setTreatments([]);

            return;
        }

        setLoading(true);

        const params = new URLSearchParams();

        if (excludeAppointmentId) {
            params.set('exclude_appointment_id', String(excludeAppointmentId));
        }

        includeTreatmentIds.forEach((id) => {
            params.append('include_treatment_ids[]', String(id));
        });

        const qs = params.toString();
        const url = `/clients/${clientId}/available-treatments${qs ? `?${qs}` : ''}`;

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                setTreatments([]);

                return;
            }

            const data = await response.json();
            setTreatments(data.treatments ?? []);
        } finally {
            setLoading(false);
        }
    }, [clientId, excludeAppointmentId, includeKey]);

    useEffect(() => {
        fetchTreatments();
    }, [fetchTreatments]);

    return { treatments, loading, refetch: fetchTreatments };
}
