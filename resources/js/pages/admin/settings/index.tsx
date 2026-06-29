import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime, routes } from '@/lib/clinic';
import type { ClinicSettings, NotificationLog } from '@/types/clinic';

type Props = {
    settings: ClinicSettings;
    recentNotifications: NotificationLog[];
};

export default function AdminSettings({ settings, recentNotifications }: Props) {
    const [whatsappEnabled, setWhatsappEnabled] = useState(settings.whatsapp_enabled);
    const [whatsappBookingEnabled, setWhatsappBookingEnabled] = useState(
        settings.whatsapp_booking_enabled,
    );

    return (
        <>
            <Head title="Configurações" />
            <div className="page-container mx-auto max-w-3xl">
                <PageHeader
                    title="Configurações da clínica"
                    description="Notificações WhatsApp, e-mail e dados da clínica"
                />

                <Form action="/admin/settings" method="put" className="mt-6 space-y-8">
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="email_days_before"
                                value={settings.email_days_before}
                            />
                            <input
                                type="hidden"
                                name="email_enabled"
                                value={settings.email_enabled ? '1' : '0'}
                            />
                            <input
                                type="hidden"
                                name="whatsapp_orientations_enabled"
                                value={settings.whatsapp_orientations_enabled ? '1' : '0'}
                            />
                            <input
                                type="hidden"
                                name="whatsapp_enabled"
                                value={whatsappEnabled ? '1' : '0'}
                            />
                            <input
                                type="hidden"
                                name="whatsapp_booking_enabled"
                                value={whatsappBookingEnabled ? '1' : '0'}
                            />

                            <Card>
                                <CardHeader>
                                    <CardTitle>Dados da clínica</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="clinic_name">Nome da clínica *</Label>
                                        <Input
                                            id="clinic_name"
                                            name="clinic_name"
                                            defaultValue={settings.clinic_name}
                                            required
                                        />
                                        <InputError message={errors.clinic_name} />
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="clinic_phone">Telefone</Label>
                                            <Input
                                                id="clinic_phone"
                                                name="clinic_phone"
                                                defaultValue={settings.clinic_phone ?? ''}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="clinic_email">E-mail</Label>
                                            <Input
                                                id="clinic_email"
                                                name="clinic_email"
                                                type="email"
                                                defaultValue={settings.clinic_email ?? ''}
                                            />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>WhatsApp</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="whatsapp_enabled"
                                            checked={whatsappEnabled}
                                            onCheckedChange={(checked) =>
                                                setWhatsappEnabled(checked === true)
                                            }
                                        />
                                        <Label htmlFor="whatsapp_enabled">
                                            Enviar lembretes via WhatsApp
                                        </Label>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="whatsapp_days_before">
                                            Dias antes do tratamento para avisar
                                        </Label>
                                        <Input
                                            id="whatsapp_days_before"
                                            name="whatsapp_days_before"
                                            type="number"
                                            min="0"
                                            max="30"
                                            defaultValue={settings.whatsapp_days_before}
                                            required
                                        />
                                        <InputError message={errors.whatsapp_days_before} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="whatsapp_message_template">
                                            Modelo do lembrete
                                        </Label>
                                        <Textarea
                                            id="whatsapp_message_template"
                                            name="whatsapp_message_template"
                                            rows={4}
                                            defaultValue={settings.whatsapp_message_template ?? ''}
                                        />
                                        <InputError message={errors.whatsapp_message_template} />
                                        <p className="text-muted-foreground text-xs">
                                            Enviado dias antes do tratamento com botões de resposta
                                            rápida &quot;Confirmar&quot; e &quot;Reagendar&quot;.
                                            Variáveis: {'{nome}'}, {'{tratamento}'}, {'{data}'},{' '}
                                            {'{hora}'}, {'{clinica}'}, {'{profissional}'}
                                        </p>
                                    </div>

                                    <div className="border-t pt-4">
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id="whatsapp_booking_enabled"
                                                checked={whatsappBookingEnabled}
                                                onCheckedChange={(checked) =>
                                                    setWhatsappBookingEnabled(checked === true)
                                                }
                                            />
                                            <Label htmlFor="whatsapp_booking_enabled">
                                                Enviar confirmação ao agendar
                                            </Label>
                                        </div>
                                        <div className="mt-4 grid gap-2">
                                            <Label htmlFor="whatsapp_booking_message_template">
                                                Mensagem de confirmação do agendamento
                                            </Label>
                                            <Textarea
                                                id="whatsapp_booking_message_template"
                                                name="whatsapp_booking_message_template"
                                                rows={4}
                                                defaultValue={
                                                    settings.whatsapp_booking_message_template ?? ''
                                                }
                                                placeholder="Olá {nome}! Seu agendamento foi confirmado..."
                                            />
                                            <InputError
                                                message={errors.whatsapp_booking_message_template}
                                            />
                                            <p className="text-muted-foreground text-xs">
                                                Enviada automaticamente após a confirmação do agendamento.
                                                Variáveis: {'{nome}'}, {'{tratamento}'}, {'{data}'},{' '}
                                                {'{hora}'}, {'{clinica}'}, {'{profissional}'}
                                            </p>
                                        </div>
                                    </div>

                                    {/* <div className="border-t pt-4">
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id="whatsapp_orientations_enabled"
                                                name="whatsapp_orientations_enabled"
                                                value="1"
                                                defaultChecked={settings.whatsapp_orientations_enabled}
                                            />
                                            <Label htmlFor="whatsapp_orientations_enabled">
                                                Enviar orientações ao agendar
                                            </Label>
                                        </div>
                                        <div className="mt-4 grid gap-2">
                                            <Label htmlFor="whatsapp_orientations_message_template">
                                                Mensagem de orientações
                                            </Label>
                                            <Textarea
                                                id="whatsapp_orientations_message_template"
                                                name="whatsapp_orientations_message_template"
                                                rows={6}
                                                defaultValue={
                                                    settings.whatsapp_orientations_message_template ?? ''
                                                }
                                                placeholder="Olá {nome}! Seguem orientações importantes..."
                                            />
                                            <p className="text-muted-foreground text-xs">
                                                Enviada apenas uma vez por cliente, no primeiro
                                                agendamento. Variáveis: {'{nome}'}, {'{tratamento}'},{' '}
                                                {'{data}'}, {'{hora}'}, {'{clinica}'}, {'{profissional}'}
                                            </p>
                                        </div>
                                    </div> */}
                                </CardContent>
                            </Card>

                            {/* <Card>
                                <CardHeader>
                                    <CardTitle>E-mail</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="email_enabled"
                                            name="email_enabled"
                                            value="1"
                                            defaultChecked={settings.email_enabled}
                                        />
                                        <Label htmlFor="email_enabled">
                                            Enviar lembretes por e-mail
                                        </Label>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="email_days_before">
                                            Dias antes do tratamento para avisar
                                        </Label>
                                        <Input
                                            id="email_days_before"
                                            name="email_days_before"
                                            type="number"
                                            min="0"
                                            max="30"
                                            defaultValue={settings.email_days_before}
                                            required
                                        />
                                        <InputError message={errors.email_days_before} />
                                    </div>
                                </CardContent>
                            </Card> */}

                            <Button type="submit" disabled={processing}>
                                Salvar configurações
                            </Button>
                        </>
                    )}
                </Form>

                {/* <Card className="mt-8">
                    <CardHeader>
                        <CardTitle>Notificações recentes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentNotifications.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                Nenhuma notificação enviada ainda.
                            </p>
                        ) : (
                            <div className="space-y-2">
                                {recentNotifications.map((log) => (
                                    <div
                                        key={log.id}
                                        className="flex items-center justify-between rounded-lg border p-3 text-sm"
                                    >
                                        <div>
                                            <p className="font-medium">{log.client?.name}</p>
                                            <p className="text-muted-foreground">
                                                {log.channel.toUpperCase()} •{' '}
                                                {log.appointment?.treatments?.map((t) => t.name).join(', ')}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <Badge
                                                variant={
                                                    log.status === 'sent' ? 'default' : 'destructive'
                                                }
                                            >
                                                {log.status === 'sent' ? 'Enviado' : 'Falhou'}
                                            </Badge>
                                            {log.sent_at && (
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    {formatDateTime(log.sent_at)}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card> */}
            </div>
        </>
    );
}

AdminSettings.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Configurações', href: routes.admin.settings() },
    ],
};
