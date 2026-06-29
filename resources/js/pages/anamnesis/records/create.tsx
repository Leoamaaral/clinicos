import { Form, Head, Link } from '@inertiajs/react';
import { AnamnesisQuestionFields } from '@/components/anamnesis-question-fields';
import { AnamnesisSubmitSection } from '@/components/anamnesis-submit-section';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useAnamnesisConsentSubmit } from '@/hooks/use-anamnesis-consent-submit';
import { routes } from '@/lib/clinic';
import type { AnamnesisQuestion, Client } from '@/types/clinic';

type Props = {
    client: Client;
    questions: AnamnesisQuestion[];
};

export default function AnamnesisRecordCreate({ client, questions }: Props) {
    const consent = useAnamnesisConsentSubmit();

    return (
        <>
            <Head title={`Anamnese - ${client.name}`} />
            <div className="mx-auto max-w-4xl p-4">
                <PageHeader
                    title="Nova anamnese"
                    description={`Cliente: ${client.name}`}
                />

                {questions.length === 0 ? (
                    <div className="text-muted-foreground mt-6 rounded-xl border p-6 text-center">
                        <p>Nenhuma pergunta ativa cadastrada.</p>
                        <Button className="mt-4" asChild>
                            <Link href={routes.anamnesisQuestions.create()}>
                                Cadastrar perguntas
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <Form
                        ref={consent.inertiaFormRef}
                        action={`/clients/${client.id}/anamnesis`}
                        method="post"
                        className="mt-6 space-y-6"
                        onBefore={consent.handleBeforeSubmit}
                    >
                        {({ processing, errors }) => (
                            <>
                                {consent.termsAccepted && (
                                    <input type="hidden" name="terms_accepted" value="1" />
                                )}
                                <AnamnesisQuestionFields
                                    questions={questions}
                                    errors={errors}
                                />

                                <div className="grid gap-4 border-t pt-5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] sm:items-start">
                                    <Label htmlFor="notes" className="sm:pt-2">
                                        Observações gerais
                                    </Label>
                                    <Textarea id="notes" name="notes" rows={3} />
                                </div>

                                <div className="flex flex-wrap items-center gap-2 pt-4">
                                    <AnamnesisSubmitSection
                                        processing={processing}
                                        submitLabel="Salvar anamnese"
                                        confirmLabel="Confirmar e salvar"
                                        modalOpen={consent.modalOpen}
                                        onModalOpenChange={consent.setModalOpen}
                                        onSubmitClick={consent.handleSubmitClick}
                                        onConfirm={consent.handleConfirm}
                                    />
                                    <Button variant="outline" asChild>
                                        <Link href={routes.clients.show(client.id)}>Cancelar</Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

AnamnesisRecordCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Clientes', href: routes.clients.index() },
        { title: 'Anamnese', href: '#' },
    ],
};
