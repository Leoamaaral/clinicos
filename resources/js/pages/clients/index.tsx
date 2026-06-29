import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { DataListCard, DataListCardActions } from '@/components/data-list-card';
import { PageHeader } from '@/components/page-header';
import { ResponsiveTable } from '@/components/responsive-table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatCpf, formatDate, formatPhone, routes } from '@/lib/clinic';
import type { Client, Paginated } from '@/types/clinic';

type Props = {
    clients: Paginated<Client>;
    filters: { search: string };
};

function ClientActions({ client }: { client: Client }) {
    return (
        <>
            <Button variant="ghost" size="icon" asChild>
                <Link href={routes.clients.show(client.id)}>
                    <Eye className="size-4" />
                </Link>
            </Button>
            <Button variant="ghost" size="icon" asChild>
                <Link href={routes.clients.edit(client.id)}>
                    <Pencil className="size-4" />
                </Link>
            </Button>
            <Button
                variant="ghost"
                size="icon"
                onClick={() => {
                    if (confirm('Remover este cliente?')) {
                        router.delete(`/clients/${client.id}`);
                    }
                }}
            >
                <Trash2 className="text-destructive size-4" />
            </Button>
        </>
    );
}

export default function ClientsIndex({ clients, filters }: Props) {
    const [search, setSearch] = useState(filters.search);

    function handleSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get(routes.clients.index(), { search }, { preserveState: true });
    }

    const emptyMessage = (
        <p className="text-muted-foreground py-8 text-center text-sm">
            Nenhum cliente encontrado.
        </p>
    );

    return (
        <>
            <Head title="Clientes" />
            <div className="page-container">
                <PageHeader
                    title="Clientes"
                    description="Gerencie o cadastro de clientes"
                    action={{ label: 'Novo cliente', href: routes.clients.create() }}
                />

                <form onSubmit={handleSearch} className="flex flex-col gap-2 sm:flex-row">
                    <div className="relative min-w-0 flex-1">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            placeholder="Buscar por nome, CPF, telefone..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                    <Button type="submit" variant="secondary" className="shrink-0 sm:w-auto">
                        Buscar
                    </Button>
                </form>

                {clients.data.length === 0 ? (
                    emptyMessage
                ) : (
                    <ResponsiveTable
                        minWidth="720px"
                        mobile={clients.data.map((client) => (
                            <DataListCard key={client.id}>
                                <p className="truncate font-medium">{client.name}</p>
                                <dl className="text-muted-foreground mt-2 space-y-1 text-sm">
                                    <div className="flex justify-between gap-3">
                                        <dt>CPF</dt>
                                        <dd className="text-foreground">{formatCpf(client.cpf)}</dd>
                                    </div>
                                    <div className="flex justify-between gap-3">
                                        <dt>Telefone</dt>
                                        <dd className="text-foreground">
                                            {formatPhone(client.phone)}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-3">
                                        <dt>Nascimento</dt>
                                        <dd className="text-foreground">
                                            {formatDate(client.birth_date)}
                                        </dd>
                                    </div>
                                </dl>
                                <DataListCardActions>
                                    <ClientActions client={client} />
                                </DataListCardActions>
                            </DataListCard>
                        ))}
                    >
                        <thead className="bg-muted/50 border-b">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">Nome</th>
                                <th className="px-4 py-3 text-left font-medium">CPF</th>
                                <th className="px-4 py-3 text-left font-medium">Telefone</th>
                                <th className="px-4 py-3 text-left font-medium">Nascimento</th>
                                <th className="px-4 py-3 text-right font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            {clients.data.map((client) => (
                                <tr key={client.id} className="border-b last:border-0">
                                    <td className="px-4 py-3 font-medium">{client.name}</td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {formatCpf(client.cpf)}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {formatPhone(client.phone)}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        {formatDate(client.birth_date)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-1">
                                            <ClientActions client={client} />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </ResponsiveTable>
                )}

                {clients.last_page > 1 && (
                    <div className="flex flex-wrap justify-center gap-2">
                        {clients.links.map((link, i) => (
                            <Button
                                key={i}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url)}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

ClientsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Clientes', href: routes.clients.index() },
    ],
};
