import { Form, Head } from '@inertiajs/react';
import { AnamnesisQuestionFields } from '@/components/anamnesis-question-fields';
import { AnamnesisSubmitSection } from '@/components/anamnesis-submit-section';
import AppLogoIcon from '@/components/app-logo-icon';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useAnamnesisConsentSubmit } from '@/hooks/use-anamnesis-consent-submit';
import type { AnamnesisQuestion } from '@/types/clinic';

type Props = {
    clientName: string;
    clinicName: string;
    questions: AnamnesisQuestion[];
    token: string;
};

export default function PublicAnamnesisFill({
    clientName,
    clinicName,
    questions,
    token,
}: Props) {
    const consent = useAnamnesisConsentSubmit();

    return (
        <>
            <Head title={`Anamnese - ${clinicName}`} />
            <div className="min-h-svh bg-background px-4 py-8">
                <div className="mx-auto max-w-2xl">
                    <div className="mb-8 flex flex-col items-center gap-3 text-center">
                        <AppLogoIcon className="size-10 fill-current text-[var(--foreground)] dark:text-white" />
                        <div>
                            <h1 className="text-2xl font-semibold">{clinicName}</h1>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Olá, {clientName}! Preencha sua ficha de anamnese abaixo.
                            </p>
                        </div>
                    </div>

                    {questions.length === 0 ? (
                        <div className="text-muted-foreground rounded-xl border p-6 text-center text-sm">
                            Nenhuma pergunta disponível no momento. Entre em contato com a clínica.
                        </div>
                    ) : (
                        <Form
                            ref={consent.inertiaFormRef}
                            action={`/anamnesis/fill/${token}`}
                            method="post"
                            className="rounded-xl border bg-card p-6 shadow-sm"
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

                                    <AnamnesisSubmitSection
                                        processing={processing}
                                        submitLabel="Enviar anamnese"
                                        className="pt-6"
                                        modalOpen={consent.modalOpen}
                                        onModalOpenChange={consent.setModalOpen}
                                        onSubmitClick={consent.handleSubmitClick}
                                        onConfirm={consent.handleConfirm}
                                    />
                                </>
                            )}
                        </Form>
                    )}
                </div>
            </div>
        </>
    );
}

PublicAnamnesisFill.layout = null;
