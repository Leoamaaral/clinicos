import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatCpf, formatPhone, routes, todayDateKey } from '@/lib/clinic';
import type { Client } from '@/types/clinic';

type Props = { client: Client };

export default function ClientsEdit({ client }: Props) {
    return (
        <>
            <Head title={`Editar - ${client.name}`} />
            <div className="mx-auto max-w-2xl p-4">
                <PageHeader title="Editar cliente" description={client.name} />

                <Form
                    action={`/clients/${client.id}`}
                    method="put"
                    className="mt-6 space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nome completo *</Label>
                                <Input id="name" name="name" defaultValue={client.name} required />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="cpf">CPF *</Label>
                                <Input
                                    id="cpf"
                                    name="cpf"
                                    defaultValue={formatCpf(client.cpf)}
                                    required
                                    maxLength={14}
                                    onChange={(e) => {
                                        e.target.value = formatCpf(e.target.value);
                                    }}
                                />
                                <InputError message={errors.cpf} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="birth_date">Data de nascimento *</Label>
                                <Input
                                    id="birth_date"
                                    name="birth_date"
                                    type="date"
                                    defaultValue={client.birth_date.split('T')[0]}
                                    max={todayDateKey()}
                                    required
                                />
                                <InputError message={errors.birth_date} />
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Telefone *</Label>
                                    <Input
                                        id="phone"
                                        name="phone"
                                        defaultValue={formatPhone(client.phone)}
                                        required
                                        onChange={(e) => {
                                            e.target.value = formatPhone(e.target.value);
                                        }}
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">E-mail</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        defaultValue={client.email ?? ''}
                                    />
                                    <InputError message={errors.email} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="notes">Observações</Label>
                                <Textarea
                                    id="notes"
                                    name="notes"
                                    rows={3}
                                    defaultValue={client.notes ?? ''}
                                />
                                <InputError message={errors.notes} />
                            </div>

                            <div className="flex gap-2 pt-4">
                                <Button type="submit" disabled={processing}>
                                    Salvar alterações
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={routes.clients.show(client.id)}>Cancelar</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

ClientsEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Clientes', href: routes.clients.index() },
        { title: 'Editar', href: '#' },
    ],
};
