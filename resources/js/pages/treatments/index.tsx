import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatDuration, routes } from '@/lib/clinic';
import type { Treatment } from '@/types/clinic';

type Props = { treatments: Treatment[] };

export default function TreatmentsIndex({ treatments }: Props) {
    return (
        <>
            <Head title="Tratamentos" />
            <div className="page-container">
                <PageHeader
                    title="Tratamentos"
                    description="Gerencie os tratamentos disponíveis"
                    action={{ label: 'Novo tratamento', href: routes.treatments.create() }}
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {treatments.length === 0 ? (
                        <p className="text-muted-foreground col-span-full py-8 text-center">
                            Nenhum tratamento cadastrado.
                        </p>
                    ) : (
                        treatments.map((treatment) => (
                            <div
                                key={treatment.id}
                                className="overflow-hidden rounded-xl border"
                            >
                                {/* {treatment.image_url ? (
                                    <img
                                        src={treatment.image_url}
                                        alt={treatment.name}
                                        className="aspect-video w-full object-cover"
                                    />
                                ) : (
                                    <div className="bg-muted flex aspect-video items-center justify-center">
                                        <span className="text-muted-foreground text-sm">
                                            Sem imagem
                                        </span>
                                    </div>
                                )} */}
                                <div className="p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <h3 className="font-semibold">{treatment.name}</h3>
                                            <p className="text-muted-foreground mt-1 text-sm">
                                                Avulso: {formatCurrency(treatment.single_price)} •
                                                Pacote 10x:{' '}
                                                {formatCurrency(treatment.package_price)}
                                            </p>
                                            <p className="text-muted-foreground text-sm">
                                                {formatDuration(treatment.duration_minutes)}
                                            </p>
                                        </div>
                                        <Badge variant={treatment.is_active ? 'default' : 'secondary'}>
                                            {treatment.is_active ? 'Ativo' : 'Inativo'}
                                        </Badge>
                                    </div>
                                    {treatment.description && (
                                        <p className="text-muted-foreground mt-2 line-clamp-2 text-sm">
                                            {treatment.description}
                                        </p>
                                    )}
                                    <div className="mt-4 flex gap-2">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={routes.treatments.edit(treatment.id)}>
                                                <Pencil className="size-4" />
                                                Editar
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                if (confirm('Remover este tratamento?')) {
                                                    router.delete(`/treatments/${treatment.id}`);
                                                }
                                            }}
                                        >
                                            <Trash2 className="text-destructive size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </>
    );
}

TreatmentsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Tratamentos', href: routes.treatments.index() },
    ],
};
