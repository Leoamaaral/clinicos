import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle,
    ChevronLeft,
    ChevronRight,
    Pencil,
    RotateCcw,
} from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import {
    addMinutesToLocalDateTime,
    appointmentStatusColors,
    formatDateKey,
    formatLocalTime,
    formatPhone,
    formatProfessionalLabel,
    parseLocalDateTime,
    routes,
    todayDateKey,
} from '@/lib/clinic';
import { cn } from '@/lib/utils';
import type { Appointment } from '@/types/clinic';

type Props = {
    appointments: Appointment[];
    filters: { date: string; professional_id: number | null };
    professionals: { id: number; name: string }[];
    statusLabels: Record<string, string>;
    counts: { total: number; completed: number; pending: number };
};

function navigateTo(params: { date: string; professional_id?: number | '' }) {
    router.get(
        routes.appointments.complete.index({
            date: params.date,
            professional_id: params.professional_id ?? '',
        }),
        {},
        { preserveState: true },
    );
}

export default function AppointmentsComplete({
    appointments,
    filters,
    professionals,
    statusLabels,
    counts,
}: Props) {
    function changeDay(offsetDays: number) {
        const date = parseLocalDateTime(`${filters.date} 12:00:00`);
        date.setDate(date.getDate() + offsetDays);
        navigateTo({
            date: formatDateKey(date),
            professional_id: filters.professional_id ?? '',
        });
    }

    function completeAppointment(id: number) {
        router.patch(routes.appointments.complete.store(id), {}, { preserveScroll: true });
    }

    function uncompleteAppointment(id: number) {
        if (!confirm('Desfazer a conclusão deste agendamento?')) {
            return;
        }

        router.patch(routes.appointments.complete.destroy(id), {}, { preserveScroll: true });
    }

    function completeAll() {
        if (
            !confirm(
                `Concluir todos os ${counts.pending} agendamento(s) pendente(s) deste dia?`,
            )
        ) {
            return;
        }

        router.post(
            routes.appointments.complete.bulk(),
            {
                date: filters.date,
                professional_id: filters.professional_id ?? '',
            },
            { preserveScroll: true },
        );
    }

    const isPending = (status: Appointment['status']) =>
        status === 'scheduled' || status === 'confirmed';

    return (
        <>
            <Head title="Concluir agendas" />
            <div className="page-container">
                <PageHeader
                    title="Concluir agendas"
                    description="Marque os atendimentos realizados"
                />

                <div className="flex flex-wrap items-end gap-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="icon" onClick={() => changeDay(-1)}>
                            <ChevronLeft className="size-4" />
                        </Button>
                        <Input
                            type="date"
                            value={filters.date}
                            onChange={(e) =>
                                navigateTo({
                                    date: e.target.value,
                                    professional_id: filters.professional_id ?? '',
                                })
                            }
                            className="w-auto"
                        />
                        <Button variant="outline" size="icon" onClick={() => changeDay(1)}>
                            <ChevronRight className="size-4" />
                        </Button>
                        <Button
                            variant="secondary"
                            onClick={() =>
                                navigateTo({
                                    date: todayDateKey(),
                                    professional_id: filters.professional_id ?? '',
                                })
                            }
                        >
                            Hoje
                        </Button>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="professional_id">Profissional</Label>
                        <NativeSelect
                            id="professional_id"
                            value={filters.professional_id ? String(filters.professional_id) : ''}
                            onChange={(e) =>
                                navigateTo({
                                    date: filters.date,
                                    professional_id: e.target.value
                                        ? Number(e.target.value)
                                        : '',
                                })
                            }
                            className="w-auto min-w-[180px]"
                        >
                            <option value="">Todos</option>
                            {professionals.map((professional) => (
                                <option key={professional.id} value={professional.id}>
                                    {professional.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <p className="text-muted-foreground text-sm">
                        {counts.completed} de {counts.total} concluído
                        {counts.total === 1 ? '' : 's'}
                    </p>
                    {counts.pending > 0 && (
                        <Button onClick={completeAll}>
                            <CheckCircle className="size-4" />
                            Concluir todos
                        </Button>
                    )}
                </div>

                {appointments.length === 0 ? (
                    <p className="text-muted-foreground rounded-xl border p-8 text-center text-sm">
                        Nenhum agendamento neste dia.
                    </p>
                ) : (
                    <div className="space-y-3">
                        {appointments.map((appointment) => {
                            const totalDuration =
                                appointment.treatments?.reduce(
                                    (sum, t) => sum + t.duration_minutes,
                                    0,
                                ) ?? 60;
                            const endTime = appointment.scheduled_end_at
                                ? formatLocalTime(appointment.scheduled_end_at)
                                : addMinutesToLocalDateTime(
                                      appointment.scheduled_at,
                                      totalDuration,
                                  );
                            const treatmentNames =
                                appointment.treatments?.map((t) => t.name).join(', ') ?? '';

                            return (
                                <div
                                    key={appointment.id}
                                    className={cn(
                                        'flex flex-col gap-4 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between',
                                        appointment.status === 'completed' && 'bg-muted/30',
                                    )}
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-mono font-semibold">
                                                {formatLocalTime(appointment.scheduled_at)}
                                                <span className="text-muted-foreground font-normal">
                                                    {' '}
                                                    – {endTime}
                                                </span>
                                            </p>
                                            <Badge
                                                className={
                                                    appointmentStatusColors[appointment.status]
                                                }
                                            >
                                                {statusLabels[appointment.status]}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 font-medium">
                                            {appointment.client?.name}
                                            {appointment.client?.phone && (
                                                <span className="text-muted-foreground font-normal">
                                                    {' '}
                                                    — {formatPhone(appointment.client.phone)}
                                                </span>
                                            )}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            {treatmentNames}
                                        </p>
                                        <p className="text-muted-foreground text-sm">
                                            {formatProfessionalLabel(appointment.professional)}
                                        </p>
                                    </div>

                                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                                        <Button variant="ghost" size="sm" asChild>
                                            <Link href={routes.appointments.edit(appointment.id)}>
                                                <Pencil className="size-4" />
                                                Editar
                                            </Link>
                                        </Button>
                                        {isPending(appointment.status) ? (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    completeAppointment(appointment.id)
                                                }
                                            >
                                                <CheckCircle className="size-4" />
                                                Concluir
                                            </Button>
                                        ) : (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    uncompleteAppointment(appointment.id)
                                                }
                                            >
                                                <RotateCcw className="size-4" />
                                                Desfazer
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}

AppointmentsComplete.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Agenda', href: routes.appointments.index() },
        { title: 'Concluir agendas', href: routes.appointments.complete.index() },
    ],
};
