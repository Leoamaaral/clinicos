import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { formatCurrency, formatTreatmentLabel } from '@/lib/clinic';
import type { Treatment } from '@/types/clinic';

export type TreatmentCatalogItem = Pick<
    Treatment,
    'id' | 'name' | 'single_price' | 'package_6_price' | 'package_price'
>;

type BillingMode = 'single' | 'package_6' | 'package';

type Props = {
    treatments: TreatmentCatalogItem[];
    selectedIds: number[];
    onToggle: (id: number) => void;
    billingMode: BillingMode;
    emptyMessage?: string;
    includeHiddenInputs?: boolean;
    error?: string;
};

function packagePriceForMode(treatment: TreatmentCatalogItem, billingMode: BillingMode): string {
    if (billingMode === 'single') {
        return treatment.single_price;
    }

    return billingMode === 'package_6'
        ? treatment.package_6_price
        : treatment.package_price;
}

function normalizeSearch(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

export function TreatmentCatalogSelect({
    treatments,
    selectedIds,
    onToggle,
    billingMode,
    emptyMessage = 'Nenhum tratamento cadastrado.',
    includeHiddenInputs = false,
    error,
}: Props) {
    const [search, setSearch] = useState('');

    const filteredTreatments = useMemo(() => {
        const query = normalizeSearch(search);

        if (query === '') {
            return treatments;
        }

        return treatments.filter((treatment) =>
            normalizeSearch(formatTreatmentLabel(treatment)).includes(query),
        );
    }, [search, treatments]);

    if (treatments.length === 0) {
        return <p className="text-muted-foreground text-sm">{emptyMessage}</p>;
    }

    return (
        <div className="space-y-2">
            <div className="overflow-hidden rounded-lg border">
                <div className="bg-muted/30 space-y-2 border-b p-3">
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Buscar tratamento..."
                        aria-label="Buscar tratamento"
                    />
                    <div className="text-muted-foreground flex items-center justify-between gap-2 text-xs">
                        <span>
                            {selectedIds.length === 0
                                ? 'Nenhum selecionado'
                                : selectedIds.length === 1
                                  ? '1 selecionado'
                                  : `${selectedIds.length} selecionados`}
                        </span>
                        <span>
                            {filteredTreatments.length} de {treatments.length}
                        </span>
                    </div>
                </div>

                <div className="max-h-72 overflow-y-auto p-2">
                    {filteredTreatments.length === 0 ? (
                        <p className="text-muted-foreground px-2 py-6 text-center text-sm">
                            Nenhum tratamento encontrado para &quot;{search}&quot;.
                        </p>
                    ) : (
                        <div className="space-y-1">
                            {filteredTreatments.map((treatment) => (
                                <label
                                    key={treatment.id}
                                    className="hover:bg-muted/50 flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5"
                                >
                                    <Checkbox
                                        checked={selectedIds.includes(treatment.id)}
                                        onCheckedChange={() => onToggle(treatment.id)}
                                    />
                                    <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                        {formatTreatmentLabel(treatment)}
                                    </span>
                                    <span className="text-muted-foreground shrink-0 text-xs tabular-nums">
                                        {formatCurrency(
                                            packagePriceForMode(treatment, billingMode),
                                        )}
                                    </span>
                                </label>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {includeHiddenInputs &&
                selectedIds.map((id) => (
                    <input key={id} type="hidden" name="treatment_ids[]" value={id} />
                ))}

            <InputError message={error} />
        </div>
    );
}
