import { AnamnesisConsentModal } from '@/components/anamnesis-consent-modal';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type AnamnesisSubmitSectionProps = {
    processing: boolean;
    submitLabel: string;
    className?: string;
    confirmLabel?: string;
    modalOpen: boolean;
    onModalOpenChange: (open: boolean) => void;
    onSubmitClick: (event: React.MouseEvent<HTMLButtonElement>) => void;
    onConfirm: () => void;
};

export function AnamnesisSubmitSection({
    processing,
    submitLabel,
    className,
    confirmLabel,
    modalOpen,
    onModalOpenChange,
    onSubmitClick,
    onConfirm,
}: AnamnesisSubmitSectionProps) {
    return (
        <>
            <div className={cn(className)}>
                <Button
                    type="submit"
                    disabled={processing}
                    className="w-full sm:w-auto"
                    onClick={onSubmitClick}
                >
                    {submitLabel}
                </Button>
            </div>

            <AnamnesisConsentModal
                open={modalOpen}
                onOpenChange={onModalOpenChange}
                onConfirm={onConfirm}
                processing={processing}
                confirmLabel={confirmLabel}
            />
        </>
    );
}
