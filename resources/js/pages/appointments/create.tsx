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
import { routes, todayDateKey } from '@/lib/clinic';
import type { Client } from '@/types/clinic';

type Props = {
    clients: Pick<Client, 'id' | 'name'>[];
    professionals: { id: number; name: string }[];
    prefill: { client_id: number | null; date: string };
};

export default function AppointmentsCreate({
    clients,
    professionals,
    prefill,
}: Props) {
    const minDate = todayDateKey();
    const [clientId, setClientId] = useState(prefill.client_id ? String(prefill.client_id) : '');
    const [date, setDate] = useState(
        prefill.date < minDate ? minDate : prefill.date,
    );
    const [selectedTreatmentIds, setSelectedTreatmentIds] = useState<number[]>([]);
    const [professionalId, setProfessionalId] = useState('');
    const [startTime, setStartTime] = useState('');
    const [endTime, setEndTime] = useState('');

    const clearTimes = () => {
        setStartTime('');
        setEndTime('');
    };

    const { treatments, loading: loadingTreatments } = useClientAvailableTreatments({
        clientId,
    });

    return (
        <>
            <Head title="Novo agendamento" />
            <div className="mx-auto max-w-2xl p-4">
                <PageHeader title="Novo agendamento" />

                <Form action="/appointments" method="post" className="mt-6 space-y-4">
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
                                    <option value="">Selecione o cliente</option>
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
                                loading={!clientId || loadingTreatments}
                                disabled={!clientId || loadingTreatments}
                                error={errors.treatment_ids}
                                emptyMessage={
                                    !clientId
                                        ? 'Selecione o cliente primeiro'
                                        : 'Nenhum tratamento com sessões disponíveis'
                                }
                            />
                            {clientId && !loadingTreatments && treatments.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    Atribua tratamentos ao cliente em{' '}
                                    <Link
                                        href={routes.clientTreatments.create(Number(clientId))}
                                        className="text-primary underline"
                                    >
                                        Tratamentos comprados
                                    </Link>
                                    .
                                </p>
                            )}

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
                                <Label htmlFor="scheduled_at">Data *</Label>
                                <Input
                                    id="scheduled_at"
                                    name="scheduled_at"
                                    type="date"
                                    min={minDate}
                                    value={date}
                                    onChange={(e) => {
                                        setDate(e.target.value);
                                        clearTimes();
                                    }}
                                    required
                                />
                                <InputError message={errors.scheduled_at} />
                            </div>

                            <TimeSlotPicker
                                date={date}
                                treatmentIds={selectedTreatmentIds}
                                professionalId={professionalId}
                                startTime={startTime}
                                endTime={endTime}
                                onStartChange={setStartTime}
                                onEndChange={setEndTime}
                                error={errors.scheduled_time}
                                endError={errors.scheduled_end_time}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="notes">Observações</Label>
                                <Textarea id="notes" name="notes" rows={3} />
                            </div>

                            <div className="flex gap-2 pt-4">
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        !startTime ||
                                        !endTime ||
                                        !clientId ||
                                        selectedTreatmentIds.length === 0
                                    }
                                >
                                    Agendar
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={routes.appointments.index(prefill.date)}>
                                        Cancelar
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

AppointmentsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Agenda', href: routes.appointments.index() },
        { title: 'Novo', href: '#' },
    ],
};
