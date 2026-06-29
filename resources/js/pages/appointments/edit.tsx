import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { TimeSlotPicker } from '@/components/time-slot-picker';
import { TreatmentMultiSelectField } from '@/components/treatment-multi-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useClientAvailableTreatments } from '@/hooks/use-client-available-treatments';
import {
    formatLocalDateInput,
    formatLocalTime,
    routes,
    todayDateKey,
} from '@/lib/clinic';
import type { Appointment, Client } from '@/types/clinic';

type Props = {
    appointment: Appointment;
    clients: Pick<Client, 'id' | 'name'>[];
    professionals: { id: number; name: string }[];
    statusLabels: Record<string, string>;
};

export default function AppointmentsEdit({
    appointment,
    clients,
    professionals,
    statusLabels,
}: Props) {
    const initialTime = formatLocalTime(appointment.scheduled_at);
    const initialEndTime = appointment.scheduled_end_at
        ? formatLocalTime(appointment.scheduled_end_at)
        : initialTime;
    const initialTreatmentIds = appointment.treatments?.map((t) => t.id) ?? [];

    const [clientId, setClientId] = useState(String(appointment.client_id));
    const [date, setDate] = useState(formatLocalDateInput(appointment.scheduled_at));
    const [selectedTreatmentIds, setSelectedTreatmentIds] = useState<number[]>(initialTreatmentIds);
    const [professionalId, setProfessionalId] = useState(
        appointment.user_id ? String(appointment.user_id) : '',
    );
    const [startTime, setStartTime] = useState(initialTime);
    const [endTime, setEndTime] = useState(initialEndTime);
    const [status, setStatus] = useState(appointment.status);

    const clearTimes = () => {
        setStartTime('');
        setEndTime('');
    };

    const { treatments, loading: loadingTreatments } = useClientAvailableTreatments({
        clientId,
        excludeAppointmentId: appointment.id,
        includeTreatmentIds: initialTreatmentIds,
    });

    const canReschedule = status === 'scheduled' || status === 'confirmed';
    const minDate = todayDateKey();

    return (
        <>
            <Head title="Editar agendamento" />
            <div className="mx-auto max-w-2xl p-4">
                <PageHeader title="Editar agendamento" />

                <Form
                    action={`/appointments/${appointment.id}`}
                    method="put"
                    className="mt-6 space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="client_id">Cliente *</Label>
                                <NativeSelect
                                    id="client_id"
                                    name="client_id"
                                    value={clientId}
                                    onChange={(e) => {
                                        setClientId(e.target.value);
                                        setSelectedTreatmentIds([]);
                                        clearTimes();
                                    }}
                                    required
                                >
                                    {clients.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                                <InputError message={errors.client_id} />
                            </div>

                            <TreatmentMultiSelectField
                                treatments={treatments}
                                selectedIds={selectedTreatmentIds}
                                onChange={(ids) => {
                                    setSelectedTreatmentIds(ids);
                                    clearTimes();
                                }}
                                loading={loadingTreatments}
                                disabled={loadingTreatments}
                                error={errors.treatment_ids}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="user_id">Profissional</Label>
                                <NativeSelect
                                    id="user_id"
                                    name="user_id"
                                    value={professionalId}
                                    onChange={(e) => {
                                        setProfessionalId(e.target.value);
                                        clearTimes();
                                    }}
                                >
                                    <option value="">Qualquer profissional</option>
                                    {professionals.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="status">Status *</Label>
                                <NativeSelect
                                    id="status"
                                    name="status"
                                    value={status}
                                    onChange={(e) => setStatus(e.target.value as Appointment['status'])}
                                    required
                                >
                                    {Object.entries(statusLabels).map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="scheduled_at">Data *</Label>
                                <Input
                                    id="scheduled_at"
                                    name="scheduled_at"
                                    type="date"
                                    min={canReschedule ? minDate : undefined}
                                    value={date}
                                    onChange={(e) => {
                                        setDate(e.target.value);
                                        clearTimes();
                                    }}
                                    required
                                />
                                <InputError message={errors.scheduled_at} />
                            </div>

                            {status !== 'cancelled' ? (
                                <TimeSlotPicker
                                    date={date}
                                    treatmentIds={selectedTreatmentIds}
                                    professionalId={professionalId}
                                    startTime={startTime}
                                    endTime={endTime}
                                    onStartChange={setStartTime}
                                    onEndChange={setEndTime}
                                    excludeAppointmentId={appointment.id}
                                    error={errors.scheduled_time}
                                    endError={errors.scheduled_end_time}
                                />
                            ) : (
                                <div className="grid gap-2">
                                    <Label htmlFor="scheduled_time">Horário</Label>
                                    <Input
                                        id="scheduled_time"
                                        name="scheduled_time"
                                        type="time"
                                        value={startTime}
                                        onChange={(e) => setStartTime(e.target.value)}
                                    />
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="notes">Observações</Label>
                                <Textarea
                                    id="notes"
                                    name="notes"
                                    rows={3}
                                    defaultValue={appointment.notes ?? ''}
                                />
                            </div>

                            <div className="flex gap-2 pt-4">
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        (status !== 'cancelled' && (!startTime || !endTime)) ||
                                        selectedTreatmentIds.length === 0
                                    }
                                >
                                    Salvar
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={routes.appointments.index(date)}>Cancelar</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

AppointmentsEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Agenda', href: routes.appointments.index() },
        { title: 'Editar', href: '#' },
    ],
};
