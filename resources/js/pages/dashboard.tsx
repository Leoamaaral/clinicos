import { Head, Link } from '@inertiajs/react';
import { Calendar, ClipboardList, Sparkles, Users } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    appointmentStatusColors,
    formatDateTime,
    formatPhone,
    formatProfessionalLabel,
    routes,
} from '@/lib/clinic';
import { dashboard } from '@/routes';
import type { Appointment } from '@/types/clinic';

type Props = {
    stats: {
        clients: number;
        appointments_today: number;
        appointments_week: number;
        treatments: number;
    };
    upcomingAppointments: Appointment[];
};

export default function Dashboard({ stats, upcomingAppointments }: Props) {
    const statCards = [
        { label: 'Clientes', value: stats.clients, icon: Users },
        { label: 'Agendamentos hoje', value: stats.appointments_today, icon: Calendar },
        { label: 'Esta semana', value: stats.appointments_week, icon: Calendar },
        { label: 'Tratamentos ativos', value: stats.treatments, icon: Sparkles },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <div className="page-container">
                <div>
                    <h1 className="text-2xl font-semibold">Dashboard</h1>
                    <p className="text-muted-foreground text-sm">
                        Visão geral
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {statCards.map((stat) => (
                        <Card key={stat.label}>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {stat.label}
                                </CardTitle>
                                <stat.icon className="text-muted-foreground size-4" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stat.value}</div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Próximos agendamentos</CardTitle>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={routes.appointments.index()}>Ver agenda</Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {upcomingAppointments.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                Nenhum agendamento próximo.
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {upcomingAppointments.map((appointment) => (
                                    <div
                                        key={appointment.id}
                                        className="flex items-center justify-between rounded-lg border p-3"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {appointment.client?.name}
                                                {appointment.client?.phone && (
                                                    <span className="text-muted-foreground font-normal">
                                                        {' '}
                                                        — {formatPhone(appointment.client.phone)}
                                                    </span>
                                                )}
                                            </p>
                                            <p className="text-muted-foreground text-sm">
                                                {appointment.treatments?.map((t) => t.name).join(', ')} •{' '}
                                                {formatProfessionalLabel(appointment.professional)}
                                            </p>
                                            <p className="text-muted-foreground text-sm">
                                                {formatDateTime(appointment.scheduled_at)}
                                            </p>
                                        </div>
                                        <Badge
                                            className={
                                                appointmentStatusColors[appointment.status]
                                            }
                                        >
                                            {appointment.status === 'scheduled'
                                                ? 'Agendado'
                                                : 'Confirmado'}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-3">
                    <Button variant="outline" className="h-auto flex-col gap-2 py-6" asChild>
                        <Link href={routes.clients.create()}>
                            <Users className="size-6" />
                            Novo cliente
                        </Link>
                    </Button>
                    <Button variant="outline" className="h-auto flex-col gap-2 py-6" asChild>
                        <Link href={routes.appointments.create()}>
                            <Calendar className="size-6" />
                            Novo agendamento
                        </Link>
                    </Button>
                    <Button variant="outline" className="h-auto flex-col gap-2 py-6" asChild>
                        <Link href={routes.anamnesisQuestions.index()}>
                            <ClipboardList className="size-6" />
                            Perguntas anamnese
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
