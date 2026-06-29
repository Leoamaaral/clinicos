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
import type { AnamnesisQuestion } from '@/types/clinic';

type Props = { question: AnamnesisQuestion };

function normalizeQuestionType(type: string): AnamnesisQuestion['type'] {
    if (type === 'textarea') {
return 'text';
}

    if (type === 'boolean') {
return 'checkbox';
}

    if (type === 'text' || type === 'select' || type === 'checkbox') {
return type;
}

    return 'text';
}

export default function AnamnesisQuestionsEdit({ question }: Props) {
    const [type, setType] = useState(normalizeQuestionType(question.type));
    const [options, setOptions] = useState<string[]>(
        question.options?.length ? question.options : [''],
    );

    return (
        <>
            <Head title="Editar pergunta" />
            <div className="mx-auto max-w-2xl p-4">
                <PageHeader title="Editar pergunta" />

                <Form
                    action={`/anamnesis-questions/${question.id}`}
                    method="put"
                    className="mt-6 space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="question">Pergunta *</Label>
                                <Input
                                    id="question"
                                    name="question"
                                    defaultValue={question.question}
                                    required
                                />
                                <InputError message={errors.question} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="type">Tipo *</Label>
                                <NativeSelect
                                    id="type"
                                    name="type"
                                    value={type}
                                    onChange={(e) => setType(e.target.value as AnamnesisQuestion['type'])}
                                >
                                    <option value="text">Texto</option>
                                    <option value="select">Seleção</option>
                                    <option value="checkbox">Sim/Não</option>
                                </NativeSelect>
                            </div>

                            {type === 'select' && (
                                <div className="grid gap-2">
                                    <Label>Opções</Label>
                                    {options.map((option, index) => (
                                        <Input
                                            key={index}
                                            name={`options[${index}]`}
                                            value={option}
                                            onChange={(e) => {
                                                const updated = [...options];
                                                updated[index] = e.target.value;
                                                setOptions(updated);
                                            }}
                                        />
                                    ))}
                                </div>
                            )}

                            <div className="flex flex-col gap-2">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="is_active"
                                        name="is_active"
                                        value="1"
                                        defaultChecked={question.is_active}
                                    />
                                    <Label htmlFor="is_active">Pergunta ativa</Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="is_required"
                                        name="is_required"
                                        value="1"
                                        defaultChecked={question.is_required}
                                    />
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

AnamnesisQuestionsEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Anamnese', href: routes.anamnesisQuestions.index() },
        { title: 'Editar', href: '#' },
    ],
};
