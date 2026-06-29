import { Head, Link } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime, routes } from '@/lib/clinic';
import type { AnamnesisRecord, Client } from '@/types/clinic';

type Props = {
    client: Client;
    record: AnamnesisRecord;
};

export default function AnamnesisRecordShow({ client, record }: Props) {
    return (
        <>
            <Head title={`Anamnese - ${formatDateTime(record.created_at)}`} />
            <div className="mx-auto max-w-2xl p-4">
                <PageHeader
                    title="Anamnese"
                    description={`${client.name} • ${formatDateTime(record.created_at)}`}
                />

                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Registrado por {record.user?.name ?? 'Sistema'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {record.answers?.map((answer) => (
                            <div key={answer.id} className="border-b pb-4 last:border-0">
                                <p className="text-muted-foreground text-sm">
                                    {answer.question?.question}
                                </p>
                                <p className="mt-1 font-medium">
                                    {answer.formatted_answer || '—'}
                                </p>
                            </div>
                        ))}

                        {record.notes && (
                            <div className="border-t pt-4">
                                <p className="text-muted-foreground text-sm">Observações</p>
                                <p className="mt-1">{record.notes}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Button className="mt-6" variant="outline" asChild>
                    <Link href={routes.clients.show(client.id)}>Voltar ao cliente</Link>
                </Button>
            </div>
        </>
    );
}

AnamnesisRecordShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Clientes', href: routes.clients.index() },
        { title: 'Anamnese', href: '#' },
    ],
};
