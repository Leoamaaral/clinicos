import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { ClientAvailableTreatment } from '@/hooks/use-client-available-treatments';
import { formatDuration, formatTreatmentLabel } from '@/lib/clinic';

type Props = {
    treatments: ClientAvailableTreatment[];
    selectedIds: number[];
    onChange: (ids: number[]) => void;
    loading?: boolean;
    disabled?: boolean;
    error?: string;
    emptyMessage?: string;
};

function formatSessionsRemaining(count: number): string {
    return count === 1 ? '1 sessão restante' : `${count} sessões restantes`;
}

export function TreatmentMultiSelect({
    treatments,
    selectedIds,
    onChange,
    loading = false,
    disabled = false,
    error,
    emptyMessage = 'Nenhum tratamento com sessões disponíveis',
}: Props) {
    const allSelected =
        treatments.length > 0 && treatments.every((t) => selectedIds.includes(t.id));

    function toggleTreatment(id: number) {
        onChange(
            selectedIds.includes(id)
                ? selectedIds.filter((t) => t !== id)
                : [...selectedIds, id],
        );
    }

    function toggleAll() {
        if (allSelected) {
            onChange([]);

            return;
        }

        onChange(treatments.map((t) => t.id));
    }

    if (loading) {
        return <p className="text-muted-foreground text-sm">Carregando tratamentos...</p>;
    }

    if (treatments.length === 0) {
        return <p className="text-muted-foreground text-sm">{emptyMessage}</p>;
    }

    return (
        <div className="space-y-2">
            <div className="overflow-hidden rounded-lg border">
                <label className="hover:bg-muted/50 flex cursor-pointer items-center gap-3 border-b px-3 py-2 font-medium">
                    <Checkbox
                        checked={allSelected}
                        onCheckedChange={toggleAll}
                        disabled={disabled}
                    />
                    <span className="flex-1 text-sm">Selecionar todos</span>
                    {selectedIds.length > 0 && (
                        <span className="text-muted-foreground text-xs">
                            {formatDuration(
                                treatments
                                    .filter((t) => selectedIds.includes(t.id))
                                    .reduce((sum, t) => sum + t.duration_minutes, 0),
                            )}
                        </span>
                    )}
                </label>

                <div className="max-h-60 overflow-y-auto p-2 sm:max-h-72">
                    <div className="space-y-1">
                        {treatments.map((treatment) => (
                            <label
                                key={treatment.id}
                                className="hover:bg-muted/50 flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5"
                            >
                                <Checkbox
                                    checked={selectedIds.includes(treatment.id)}
                                    onCheckedChange={() => toggleTreatment(treatment.id)}
                                    disabled={disabled}
                                />
                                <span className="min-w-0 flex-1 text-sm">
                                    <span className="font-medium">
                                        {formatTreatmentLabel(treatment)}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {' '}
                                        — {formatDuration(treatment.duration_minutes)} (
                                        {formatSessionsRemaining(treatment.sessions_remaining)})
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>
                </div>
            </div>

            {selectedIds.map((id) => (
                <input key={id} type="hidden" name="treatment_ids[]" value={id} />
            ))}

            <InputError message={error} />
        </div>
    );
}

export function TreatmentMultiSelectField({
    label = 'Tratamentos *',
    ...props
}: Props & { label?: string }) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <TreatmentMultiSelect {...props} />
        </div>
    );
}
