import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import type { AnamnesisQuestion } from '@/types/clinic';

export function AnamnesisQuestionField({ question }: { question: AnamnesisQuestion }) {
    const name = `answers[${question.id}]`;
    const legacyType = question.type as string;
    const type =
        legacyType === 'textarea'
            ? 'text'
            : legacyType === 'boolean'
              ? 'checkbox'
              : question.type;

    switch (type) {
        case 'checkbox':
            return (
                <div className="space-y-3">
                    <div className="flex gap-6">
                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="radio"
                                name={`${name}[value]`}
                                value="Sim"
                                required={question.is_required}
                                className="border-input size-4 border"
                            />
                            <span className="text-sm">Sim</span>
                        </label>
                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="radio"
                                name={`${name}[value]`}
                                value="Não"
                                className="border-input size-4 border"
                            />
                            <span className="text-sm">Não</span>
                        </label>
                    </div>
                    <Input
                        name={`${name}[detail]`}
                        placeholder="Detalhes (opcional)"
                    />
                </div>
            );
        case 'select':
            return (
                <NativeSelect name={name} required={question.is_required}>
                    <option value="">Selecione</option>
                    {question.options?.map((opt) => (
                        <option key={opt} value={opt}>
                            {opt}
                        </option>
                    ))}
                </NativeSelect>
            );
        default:
            return (
                <Textarea name={name} rows={3} required={question.is_required} />
            );
    }
}

export function AnamnesisQuestionFields({
    questions,
    errors,
}: {
    questions: AnamnesisQuestion[];
    errors: Record<string, string | undefined>;
}) {
    return (
        <>
            {questions.map((question) => (
                <div
                    key={question.id}
                    className="grid gap-4 border-b py-5 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] sm:items-start"
                >
                    <Label className="text-sm leading-snug font-medium sm:pt-2">
                        {question.question}
                        {question.is_required && (
                            <span className="text-destructive ml-1">*</span>
                        )}
                    </Label>
                    <div className="space-y-1">
                        <AnamnesisQuestionField question={question} />
                        <InputError message={errors[`answers.${question.id}`]} />
                    </div>
                </div>
            ))}
        </>
    );
}
