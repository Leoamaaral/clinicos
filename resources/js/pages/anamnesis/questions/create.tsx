import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { routes } from '@/lib/clinic';

export default function AnamnesisQuestionsCreate() {
    const [type, setType] = useState('text');
    const [options, setOptions] = useState<string[]>(['']);

    function addOption() {
        setOptions([...options, '']);
    }

    function updateOption(index: number, value: string) {
        const updated = [...options];
        updated[index] = value;
        setOptions(updated);
    }

    function removeOption(index: number) {
        setOptions(options.filter((_, i) => i !== index));
    }

    return (
        <>
            <Head title="Nova pergunta" />
            <div className="mx-auto max-w-2xl p-4">
                <PageHeader title="Nova pergunta da anamnese" />

                <Form action="/anamnesis-questions" method="post" className="mt-6 space-y-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="question">Pergunta *</Label>
                                <Input id="question" name="question" required />
                                <InputError message={errors.question} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="type">Tipo *</Label>
                                <NativeSelect
                                    id="type"
                                    name="type"
                                    value={type}
                                    onChange={(e) => setType(e.target.value)}
                                >
                                    <option value="text">Texto</option>
                                    <option value="select">Seleção</option>
                                    <option value="checkbox">Sim/Não</option>
                                </NativeSelect>
                                <InputError message={errors.type} />
                            </div>

                            {type === 'select' && (
                                <div className="grid gap-2">
                                    <Label>Opções</Label>
                                    {options.map((option, index) => (
                                        <div key={index} className="flex gap-2">
                                            <Input
                                                name={`options[${index}]`}
                                                value={option}
                                                onChange={(e) =>
                                                    updateOption(index, e.target.value)
                                                }
                                                placeholder={`Opção ${index + 1}`}
                                            />
                                            {options.length > 1 && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    onClick={() => removeOption(index)}
                                                >
                                                    Remover
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                    <Button type="button" variant="outline" onClick={addOption}>
                                        Adicionar opção
                                    </Button>
                                </div>
                            )}

                            <div className="flex flex-col gap-2">
                                <div className="flex items-center gap-2">
                                    <Checkbox id="is_active" name="is_active" value="1" defaultChecked />
                                    <Label htmlFor="is_active">Pergunta ativa</Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Checkbox id="is_required" name="is_required" value="1" />
                                    <Label htmlFor="is_required">Resposta obrigatória</Label>
                                </div>
                            </div>

                            <div className="flex gap-2 pt-4">
                                <Button type="submit" disabled={processing}>
                                    Salvar
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={routes.anamnesisQuestions.index()}>Cancelar</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

AnamnesisQuestionsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Anamnese', href: routes.anamnesisQuestions.index() },
        { title: 'Nova', href: routes.anamnesisQuestions.create() },
    ],
};
