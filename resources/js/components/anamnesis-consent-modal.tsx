import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

export const ANAMNESIS_CONSENT_PARAGRAPHS = [
    'Declaro que todas as informações acima são verdadeiras e me responsabilizo por sua veracidade. Estou ciente de que omitir informações pode comprometer minha segurança durante o procedimento de depilação a laser.',
    'Também declaro estar ciente dos possíveis efeitos temporários, como vermelhidão, sensibilidade e ardência leve após a sessão.',
    'Autorizo a realização do procedimento conforme avaliação profissional.',
] as const;

type AnamnesisConsentModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
    processing?: boolean;
    confirmLabel?: string;
};

export function AnamnesisConsentModal({
    open,
    onOpenChange,
    onConfirm,
    processing = false,
    confirmLabel = 'Confirmar e enviar',
}: AnamnesisConsentModalProps) {
    const [accepted, setAccepted] = useState(false);

    useEffect(() => {
        if (!open) {
            setAccepted(false);
        }
    }, [open]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Termo de ciência e responsabilidade</DialogTitle>
                    <DialogDescription className="sr-only">
                        Leia e aceite o termo para enviar a ficha de anamnese.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3 text-sm leading-relaxed">
                    {ANAMNESIS_CONSENT_PARAGRAPHS.map((paragraph) => (
                        <p key={paragraph}>{paragraph}</p>
                    ))}
                </div>

                <div className="flex items-start gap-3 rounded-lg border p-4">
                    <Checkbox
                        id="anamnesis-consent"
                        checked={accepted}
                        onCheckedChange={(checked) => setAccepted(checked === true)}
                    />
                    <Label
                        htmlFor="anamnesis-consent"
                        className="cursor-pointer text-sm leading-snug font-normal"
                    >
                        Li e concordo com o termo de ciência e responsabilidade
                    </Label>
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        onClick={onConfirm}
                        disabled={!accepted || processing}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
