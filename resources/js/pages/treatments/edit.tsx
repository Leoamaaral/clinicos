import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { routes } from '@/lib/clinic';
import type { Treatment } from '@/types/clinic';

type Props = {
    treatment: Treatment;
};

export default function TreatmentsEdit({ treatment }: Props) {
    return (
        <>
            <Head title={`Editar - ${treatment.name}`} />
            <div className="page-container mx-auto w-full max-w-2xl">
                <PageHeader title="Editar tratamento" description={treatment.name} />

                {/* {treatment.image_url && (
                    <img
                        src={treatment.image_url}
                        alt={treatment.name}
                        className="mb-6 aspect-video w-full rounded-xl object-cover"
                    />
                )} */}

                <Form
                    action={`/treatments/${treatment.id}`}
                    method="put"
                    encType="multipart/form-data"
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nome *</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={treatment.name}
                                    required
                                    className="w-full"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Descrição</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    rows={4}
                                    defaultValue={treatment.description ?? ''}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="space-y-2">
                                <div className="grid grid-cols-1 items-start gap-4 sm:grid-cols-3">
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="single_price">Valor avulso (R$) *</Label>
                                        <Input
                                            id="single_price"
                                            name="single_price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            defaultValue={treatment.single_price}
                                            required
                                            className="w-full"
                                        />
                                        <InputError message={errors.single_price} />
                                    </div>
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="package_6_price">Pacote 6 sessões (R$) *</Label>
                                        <Input
                                            id="package_6_price"
                                            name="package_6_price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            defaultValue={treatment.package_6_price}
                                            required
                                            className="w-full"
                                        />
                                        <InputError message={errors.package_6_price} />
                                    </div>
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="package_price">Pacote 10 sessões (R$) *</Label>
                                        <Input
                                            id="package_price"
                                            name="package_price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            defaultValue={treatment.package_price}
                                            required
                                            className="w-full"
                                        />
                                        <InputError message={errors.package_price} />
                                    </div>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    Os pacotes já incluem desconto em relação às sessões avulsas.
                                </p>
                            </div>

                            <div className="grid min-w-0 gap-2 sm:max-w-xs">
                                <Label htmlFor="duration_minutes">Duração da sessão (min) *</Label>
                                <Input
                                    id="duration_minutes"
                                    name="duration_minutes"
                                    type="number"
                                    min="15"
                                    max="480"
                                    step="15"
                                    defaultValue={treatment.duration_minutes}
                                    required
                                    className="w-full"
                                />
                                <InputError message={errors.duration_minutes} />
                            </div>

                            {/* <div className="grid gap-2">
                                <Label htmlFor="image">Nova imagem</Label>
                                <Input id="image" name="image" type="file" accept="image/*" />
                                <InputError message={errors.image} />
                            </div> */}

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_active"
                                    name="is_active"
                                    value="1"
                                    defaultChecked={treatment.is_active}
                                />
                                <Label htmlFor="is_active" className="font-normal">
                                    Tratamento ativo
                                </Label>
                            </div>

                            <div className="flex flex-wrap gap-2 border-t pt-6">
                                <Button type="submit" disabled={processing}>
                                    Salvar
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={routes.treatments.index()}>Cancelar</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

TreatmentsEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Tratamentos', href: routes.treatments.index() },
        { title: 'Editar', href: '#' },
    ],
};
