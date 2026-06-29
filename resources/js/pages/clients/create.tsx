import { Form, Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatCpf, formatPhone, routes, todayDateKey } from '@/lib/clinic';

export default function ClientsCreate() {
    return (
        <>
            <Head title="Novo cliente" />
            <div className="mx-auto max-w-2xl p-4">
                <PageHeader title="Novo cliente" description="Cadastre um novo cliente" />

                <Form action="/clients" method="post" className="mt-6 space-y-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nome completo *</Label>
                                <Input id="name" name="name" required />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="cpf">CPF *</Label>
                                <Input
                                    id="cpf"
                                    name="cpf"
                                    required
                                    placeholder="000.000.000-00"
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
                                        required
                                        placeholder="(00) 00000-0000"
                                        onChange={(e) => {
                                            e.target.value = formatPhone(e.target.value);
                                        }}
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">E-mail</Label>
                                    <Input id="email" name="email" type="email" />
                                    <InputError message={errors.email} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="notes">Observações</Label>
                                <Textarea id="notes" name="notes" rows={3} />
                                <InputError message={errors.notes} />
                            </div>

                            <div className="flex gap-2 pt-4">
                                <Button type="submit" disabled={processing}>
                                    Salvar cliente
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={routes.clients.index()}>Cancelar</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

ClientsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Clientes', href: routes.clients.index() },
        { title: 'Novo', href: routes.clients.create() },
    ],
};
