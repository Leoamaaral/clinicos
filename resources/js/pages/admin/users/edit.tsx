import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { routes } from '@/lib/clinic';
import type { AdminUser } from '@/types/clinic';

type Props = { user: AdminUser };

export default function AdminUsersEdit({ user }: Props) {
    return (
        <>
            <Head title={`Editar - ${user.name}`} />
            <div className="mx-auto max-w-2xl p-4">
                <PageHeader title="Editar usuário" description={user.name} />

                <Form
                    action={`/admin/users/${user.id}`}
                    method="put"
                    className="mt-6 space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nome *</Label>
                                <Input id="name" name="name" defaultValue={user.name} required />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">E-mail *</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    defaultValue={user.email}
                                    required
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="password">Nova senha</Label>
                                    <Input id="password" name="password" type="password" />
                                    <InputError message={errors.password} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">Confirmar senha</Label>
                                    <Input
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        type="password"
                                    />
                                </div>
                            </div>

                            <div className="flex gap-2 pt-4">
                                <Button type="submit" disabled={processing}>
                                    Salvar
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={routes.admin.users.index()}>Cancelar</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

AdminUsersEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Usuários', href: routes.admin.users.index() },
        { title: 'Editar', href: '#' },
    ],
};
