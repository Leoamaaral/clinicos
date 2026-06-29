import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { DataListCard, DataListCardActions } from '@/components/data-list-card';
import { PageHeader } from '@/components/page-header';
import { ResponsiveTable } from '@/components/responsive-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { routes } from '@/lib/clinic';
import type { AdminUser } from '@/types/clinic';

type Props = { users: AdminUser[] };

function UserRoleBadge({ role }: { role: AdminUser['role'] }) {
    return (
        <Badge variant={role === 'admin' ? 'default' : 'secondary'}>
            {role === 'admin' ? 'Administrador' : 'Profissional'}
        </Badge>
    );
}

function UserActions({ user }: { user: AdminUser }) {
    return (
        <>
            <Button variant="ghost" size="icon" asChild>
                <Link href={routes.admin.users.edit(user.id)}>
                    <Pencil className="size-4" />
                </Link>
            </Button>
            {user.role !== 'admin' && (
                <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => {
                        if (confirm('Remover este profissional?')) {
                            router.delete(`/admin/users/${user.id}`);
                        }
                    }}
                >
                    <Trash2 className="text-destructive size-4" />
                </Button>
            )}
        </>
    );
}

export default function AdminUsersIndex({ users }: Props) {
    return (
        <>
            <Head title="Profissionais" />
            <div className="page-container">
                <PageHeader
                    title="Profissionais"
                    description="Cadastre e gerencie os profissionais que utilizam o sistema"
                    action={{ label: 'Novo profissional', href: routes.admin.users.create() }}
                />

                <ResponsiveTable
                    minWidth="560px"
                    mobile={users.map((user) => (
                        <DataListCard key={user.id}>
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">{user.name}</p>
                                    <p className="text-muted-foreground mt-0.5 truncate text-sm">
                                        {user.email}
                                    </p>
                                </div>
                                <UserRoleBadge role={user.role} />
                            </div>
                            <DataListCardActions>
                                <UserActions user={user} />
                            </DataListCardActions>
                        </DataListCard>
                    ))}
                >
                    <thead className="bg-muted/50 border-b">
                        <tr>
                            <th className="px-4 py-3 text-left font-medium">Nome</th>
                            <th className="px-4 py-3 text-left font-medium">E-mail</th>
                            <th className="px-4 py-3 text-left font-medium">Perfil</th>
                            <th className="px-4 py-3 text-right font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.map((user) => (
                            <tr key={user.id} className="border-b last:border-0">
                                <td className="px-4 py-3 font-medium">{user.name}</td>
                                <td className="px-4 py-3">{user.email}</td>
                                <td className="px-4 py-3">
                                    <UserRoleBadge role={user.role} />
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-1">
                                        <UserActions user={user} />
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </ResponsiveTable>
            </div>
        </>
    );
}

AdminUsersIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Administração', href: routes.admin.users.index() },
        { title: 'Usuários', href: routes.admin.users.index() },
    ],
};
