import { Head, Link, router } from '@inertiajs/react';
import { Calendar, ClipboardList, MessageCircle, Package, Pencil, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    appointmentStatusColors,
    formatCpf,
    formatCurrency,
    formatDate,
    formatDateTime,
    formatProfessionalLabel,
    formatPhone,
    formatTreatmentLabel,
    purchasePaymentMethodLabels,
    routes,
} from '@/lib/clinic';
import type {
    AnamnesisRecord,
    Appointment,
    Client,
    ClientTreatmentPurchase,
} from '@/types/clinic';

type Props = {
    client: Client & {
        anamnesis_records: AnamnesisRecord[];
        treatment_purchases: ClientTreatmentPurchase[];
    };
    pastAppointments: Appointment[];
    upcomingAppointments: Appointment[];
    statusLabels: Record<string, string>;
    purchaseTypeLabels: Record<string, string>;
};

function AppointmentList({
    appointments,
    statusLabels,
    emptyMessage,
}: {
    appointments: Appointment[];
    statusLabels: Record<string, string>;
    emptyMessage: string;
}) {
    if (appointments.length === 0) {
        return <p className="text-muted-foreground text-sm">{emptyMessage}</p>;
    }

    return (
        <div className="space-y-3">
            {appointments.map((appointment) => (
                <div
                    key={appointment.id}
                    className="flex items-center justify-between rounded-lg border p-3"
                >
                    <div>
                        <p className="font-medium">
                            {appointment.treatments && appointment.treatments.length > 0
                                ? appointment.treatments.map((t) => formatTreatmentLabel(t)).join(', ')
                                : '—'}
                        </p>
                        <p className="text-muted-foreground text-sm">
                            {formatProfessionalLabel(appointment.professional)} •{' '}
                            {formatDateTime(appointment.scheduled_at)}
                        </p>
                    </div>
                    <Badge className={appointmentStatusColors[appointment.status]}>
                        {statusLabels[appointment.status]}
                    </Badge>
                </div>
            ))}
        </div>
    );
}

export default function ClientsShow({
    client,
    pastAppointments,
    upcomingAppointments,
    statusLabels,
    purchaseTypeLabels,
}: Props) {
    const purchases = client.treatment_purchases ?? [];

    return (
        <>
            <Head title={client.name} />
            <div className="page-container">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{client.name}</h1>
                        <div className="text-muted-foreground mt-2 space-y-1 text-sm">
                            <p>CPF: {formatCpf(client.cpf)}</p>
                            <p>Telefone: {formatPhone(client.phone)}</p>
                            {client.email && <p>E-mail: {client.email}</p>}
                            <p>Nascimento: {formatDate(client.birth_date)}</p>
                            {client.notes && <p>Obs: {client.notes}</p>}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={routes.clients.edit(client.id)}>
                                <Pencil className="size-4" />
                                Editar
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={routes.clientTreatments.create(client.id)}>
                                <Package className="size-4" />
                                Atribuir tratamentos
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={routes.clients.anamnesisCreate(client.id)}>
                                <ClipboardList className="size-4" />
                                Nova anamnese
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => {
                                if (
                                    confirm(
                                        'Enviar link de anamnese por WhatsApp para este cliente?',
                                    )
                                ) {
                                    router.post(routes.clients.anamnesisRequest(client.id));
                                }
                            }}
                        >
                            <MessageCircle className="size-4" />
                            Solicitar anamnese
                        </Button>
                        <Button variant="secondary" asChild>
                            <Link
                                href={routes.appointments.create({
                                    client_id: client.id,
                                })}
                            >
                                <Calendar className="size-4" />
                                Agendar
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="mt-8 grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Agendamentos futuros</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <AppointmentList
                                appointments={upcomingAppointments}
                                statusLabels={statusLabels}
                                emptyMessage="Nenhum agendamento futuro."
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Já agendado</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <AppointmentList
                                appointments={pastAppointments}
                                statusLabels={statusLabels}
                                emptyMessage="Nenhum agendamento passado ou concluído."
                            />
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader className="flex flex-row items-center justify-between gap-2">
                            <CardTitle>Tratamentos comprados</CardTitle>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={routes.clientTreatments.create(client.id)}>
                                    Atribuir
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {purchases.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    Nenhum tratamento atribuído a este cliente.
                                </p>
                            ) : (
                                <div className="space-y-4">
                                    {purchases.map((purchase) => (
                                        <div key={purchase.id} className="rounded-lg border p-4">
                                            <div className="flex flex-wrap items-start justify-between gap-2">
                                                <div>
                                                    <p className="font-medium">
                                                        {purchaseTypeLabels[purchase.purchase_type]}
                                                        {purchase.is_courtesy && (
                                                            <Badge
                                                                variant="secondary"
                                                                className="ml-2 align-middle"
                                                            >
                                                                Cortesia
                                                            </Badge>
                                                        )}
                                                    </p>
                                                    <p className="text-muted-foreground text-sm">
                                                        {formatDate(purchase.purchased_at)} •{' '}
                                                        {purchase.is_courtesy ? (
                                                            <>
                                                                Cortesia — {formatCurrency(0)}
                                                                {purchase.calculated_price &&
                                                                    parseFloat(purchase.calculated_price) >
                                                                        0 && (
                                                                        <>
                                                                            {' '}
                                                                            (ref.{' '}
                                                                            {formatCurrency(
                                                                                purchase.calculated_price,
                                                                            )}
                                                                            )
                                                                        </>
                                                                    )}
                                                            </>
                                                        ) : (
                                                            <>
                                                                {formatCurrency(purchase.total_price)}
                                                                {purchase.discount_percent &&
                                                                    parseFloat(purchase.discount_percent) >
                                                                        0 && (
                                                                        <>
                                                                            {' '}
                                                                            (desconto{' '}
                                                                            {parseFloat(
                                                                                purchase.discount_percent,
                                                                            )
                                                                                .toFixed(2)
                                                                                .replace('.', ',')}
                                                                            %)
                                                                        </>
                                                                    )}
                                                            </>
                                                        )}
                                                    </p>
                                                </div>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => {
                                                        if (
                                                            confirm(
                                                                'Remover esta compra de tratamentos? Você poderá atribuir novamente em seguida.',
                                                            )
                                                        ) {
                                                            router.delete(
                                                                routes.clientTreatments.destroy(
                                                                    client.id,
                                                                    purchase.id,
                                                                ),
                                                            );
                                                        }
                                                    }}
                                                >
                                                    <Trash2 className="size-4" />
                                                    Excluir
                                                </Button>
                                            </div>
                                            {purchase.payments &&
                                                purchase.payments.length > 0 &&
                                                !purchase.is_courtesy && (
                                                <ul className="mt-2 space-y-1 text-sm">
                                                    {purchase.payments.map((payment) => (
                                                        <li
                                                            key={payment.id}
                                                            className="text-muted-foreground flex flex-wrap justify-between gap-2"
                                                        >
                                                            <span>
                                                                {
                                                                    purchasePaymentMethodLabels[
                                                                        payment.method
                                                                    ]
                                                                }
                                                                {payment.method === 'card' &&
                                                                    (payment.card_type === 'debit'
                                                                        ? ' • débito'
                                                                        : payment.installments &&
                                                                          ` • crédito ${payment.installments}x`)}
                                                            </span>
                                                            <span>{formatCurrency(payment.amount)}</span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                            {purchase.items && purchase.items.length > 0 && (
                                                <ul className="mt-3 space-y-2">
                                                    {purchase.items.map((item) => (
                                                        <li
                                                            key={item.id}
                                                            className="flex flex-wrap items-center justify-between gap-2 rounded-md bg-muted/40 px-3 py-2 text-sm"
                                                        >
                                                            <span className="font-medium">
                                                                {item.treatment
                                                                    ? formatTreatmentLabel(
                                                                          item.treatment,
                                                                      )
                                                                    : '—'}
                                                            </span>
                                                            <span className="text-muted-foreground">
                                                                {item.sessions_used}/
                                                                {item.sessions_total} sessões
                                                                utilizadas
                                                            </span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                            {purchase.notes && (
                                                <p className="text-muted-foreground mt-2 text-sm">
                                                    {purchase.notes}
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Histórico de anamnese</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {client.anamnesis_records.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    Nenhuma anamnese registrada.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {client.anamnesis_records.map((record) => (
                                        <Link
                                            key={record.id}
                                            href={routes.clients.anamnesisShow(
                                                client.id,
                                                record.id,
                                            )}
                                            className="hover:bg-muted/50 block rounded-lg border p-3 transition-colors"
                                        >
                                            <p className="font-medium">
                                                {formatDateTime(record.created_at)}
                                            </p>
                                            <p className="text-muted-foreground text-sm">
                                                {record.user?.name ?? 'Cliente'} •{' '}
                                                {record.answers?.length ?? 0} respostas
                                            </p>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

ClientsShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Clientes', href: routes.clients.index() },
        { title: 'Detalhes', href: '#' },
    ],
};
