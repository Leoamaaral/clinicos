import { Head, Link, router } from '@inertiajs/react';
import { CalendarCheck, ChevronLeft, ChevronRight, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    addMinutesToLocalDateTime,
    appointmentDateKey,
    appointmentStatusColors,
    formatDateKey,
    formatLocalTime,
    formatPhone,
    formatProfessionalLabel,
    formatWeekRange,
    getWeekDays,
    parseLocalDateTime,
    routes,
    todayDateKey,
} from '@/lib/clinic';
import { cn } from '@/lib/utils';
import type { Appointment, Client, Treatment } from '@/types/clinic';

type Props = {
    appointments: Appointment[];
    filters: { date: string; week_start: string; week_end: string };
    statusLabels: Record<string, string>;
    clients: Pick<Client, 'id' | 'name'>[];
    treatments: Pick<Treatment, 'id' | 'name' | 'single_price'>[];
    professionals: { id: number; name: string }[];
};

export default function AppointmentsIndex({
    appointments,
    filters,
    statusLabels,
}: Props) {
    const weekDays = useMemo(
        () => getWeekDays(filters.week_start),
        [filters.week_start],
    );

    const appointmentsByDay = useMemo(() => {
        const grouped: Record<string, Appointment[]> = {};

        for (const day of weekDays) {
            grouped[day.date] = [];
        }

        for (const appointment of appointments) {
            const key = appointmentDateKey(appointment.scheduled_at);
            grouped[key]?.push(appointment);
        }

        return grouped;
    }, [appointments, weekDays]);

    function changeWeek(offsetWeeks: number) {
        const date = parseLocalDateTime(`${filters.date} 12:00:00`);
        date.setDate(date.getDate() + offsetWeeks * 7);
        router.get(routes.appointments.index(formatDateKey(date)));
    }

    function goToToday() {
        router.get(routes.appointments.index(todayDateKey()));
    }

    return (
        <>
            <Head title="Agenda" />
            <div className="page-container">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Agenda</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Calendário semanal de agendamentos
                        </p>
                    </div>
                    <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                        <Button variant="outline" asChild className="w-full sm:w-auto">
                            <Link
                                href={routes.appointments.complete.index({
                                    date: filters.date,
                                })}
                            >
                                <CalendarCheck className="size-4" />
                                Concluir do dia
                            </Link>
                        </Button>
                        <Button asChild className="w-full sm:w-auto">
                            <Link
                                href={routes.appointments.create({ date: filters.date })}
                            >
                                <Plus className="size-4" />
                                Novo agendamento
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-4">
                    <Button variant="outline" size="icon" onClick={() => changeWeek(-1)}>
                        <ChevronLeft className="size-4" />
                    </Button>
                    <Input
                        type="date"
                        value={filters.date}
                        onChange={(e) =>
                            router.get(routes.appointments.index(e.target.value))
                        }
                        className="w-auto"
                    />
                    <Button variant="outline" size="icon" onClick={() => changeWeek(1)}>
                        <ChevronRight className="size-4" />
                    </Button>
                    <Button variant="secondary" onClick={goToToday}>
                        Hoje
                    </Button>
                    <span className="text-muted-foreground text-sm capitalize">
                        {formatWeekRange(filters.week_start, filters.week_end)}
                    </span>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <div className="grid min-w-[900px] grid-cols-7 divide-x">
                        {weekDays.map((day) => {
                            const dayAppointments = appointmentsByDay[day.date] ?? [];

                            return (
                                <div key={day.date} className="flex min-h-[420px] flex-col">
                                    <div
                                        className={cn(
                                            'border-b px-3 py-3 text-center',
                                            day.isToday && 'bg-primary/5',
                                        )}
                                    >
                                        <p className="text-muted-foreground text-xs uppercase">
                                            {day.dayName}
                                        </p>
                                        <p
                                            className={cn(
                                                'mt-1 text-lg font-semibold',
                                                day.isToday &&
                                                    'bg-primary text-primary-foreground inline-flex size-8 items-center justify-center rounded-full',
                                            )}
                                        >
                                            {day.dayNumber}
                                        </p>
                                    </div>

                                    <div className="flex flex-1 flex-col gap-2 p-2">
                                        {dayAppointments.length === 0 ? (
                                            <p className="text-muted-foreground m-auto px-1 text-center text-xs">
                                                Sem agendamentos
                                            </p>
                                        ) : (
                                            dayAppointments.map((appointment) => {
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
                                                    appointment.treatments
                                                        ?.map((t) => t.name)
                                                        .join(', ') ?? '';

                                                return (
                                                    <div
                                                        key={appointment.id}
                                                        className="rounded-lg border bg-card p-2 text-xs shadow-xs"
                                                    >
                                                        <div className="flex items-start justify-between gap-1">
                                                            <p className="font-mono font-semibold">
                                                                {formatLocalTime(appointment.scheduled_at)}
                                                                <span className="text-muted-foreground font-normal">
                                                                    {' '}
                                                                    – {endTime}
                                                                </span>
                                                            </p>
                                                            <Badge
                                                                className={cn(
                                                                    'shrink-0 px-1.5 py-0 text-[10px]',
                                                                    appointmentStatusColors[
                                                                        appointment.status
                                                                    ],
                                                                )}
                                                            >
                                                                {statusLabels[appointment.status]}
                                                            </Badge>
                                                        </div>
                                                        <p className="mt-1 font-medium leading-tight">
                                                            {appointment.client?.name}
                                                        </p>
                                                        {appointment.client?.phone && (
                                                            <p className="text-muted-foreground mt-0.5">
                                                                {formatPhone(appointment.client.phone)}
                                                            </p>
                                                        )}
                                                        <p className="text-muted-foreground mt-1 line-clamp-2">
                                                            {treatmentNames}
                                                        </p>
                                                        <p className="text-muted-foreground mt-0.5">
                                                            {formatProfessionalLabel(
                                                                appointment.professional,
                                                            )}
                                                        </p>
                                                        <div className="mt-2 flex gap-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-7"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={routes.appointments.edit(
                                                                        appointment.id,
                                                                    )}
                                                                >
                                                                    <Pencil className="size-3" />
                                                                </Link>
                                                            </Button>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-7"
                                                                onClick={() => {
                                                                    if (
                                                                        confirm(
                                                                            'Remover este agendamento?',
                                                                        )
                                                                    ) {
                                                                        router.delete(
                                                                            `/appointments/${appointment.id}`,
                                                                        );
                                                                    }
                                                                }}
                                                            >
                                                                <Trash2 className="text-destructive size-3" />
                                                            </Button>
                                                        </div>
                                                    </div>
                                                );
                                            })
                                        )}

                                        {!day.isPast && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="mt-auto h-8 text-xs"
                                                asChild
                                            >
                                                <Link
                                                    href={routes.appointments.create({
                                                        date: day.date,
                                                    })}
                                                >
                                                    <Plus className="size-3" />
                                                    Agendar
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </>
    );
}

AppointmentsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Agenda', href: routes.appointments.index() },
    ],
};
