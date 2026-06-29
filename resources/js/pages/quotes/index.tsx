import { Head, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { PurchaseDiscountValidation } from '@/components/purchase-discount-validation';
import { TreatmentCatalogSelect } from '@/components/treatment-catalog-select';
import { Label } from '@/components/ui/label';
import {
    applyPurchaseDiscount,
    createEmptyPurchasePayment,
    maxPurchaseDiscountPercent,
    purchasePaymentsTotal,
    routes,
    todayDateKey,
    type PurchaseDiscountRules,
    type PurchasePaymentInput,
} from '@/lib/clinic';
import type { Auth } from '@/types/auth';
import type { Treatment } from '@/types/clinic';

type ComboInfo = {
    sessions_count: number;
    min_treatment_count: number;
    extra_discount_percent: string;
};

type PreviewLine = {
    treatment_name: string;
    base_price: number;
    unit_price: number;
    sessions_total: number;
    combo_no_discount: boolean;
};

type Preview = {
    purchase_type_label: string;
    calculated_price: number;
    discount_percent: number;
    total_price: number;
    max_discount_percent: number;
    lines: PreviewLine[];
    is_combo: boolean;
};

type Props = {
    treatments: Pick<
        Treatment,
        'id' | 'name' | 'single_price' | 'package_6_price' | 'package_price'
    >[];
    combos: ComboInfo[];
    discountRules: PurchaseDiscountRules;
};

type BillingMode = 'single' | 'package_6' | 'package';

function validPaymentsForPreview(payments: PurchasePaymentInput[]): PurchasePaymentInput[] {
    return payments.filter((payment) => parseFloat(payment.amount) > 0);
}

export default function QuotesIndex({ treatments, combos, discountRules }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isAdmin = auth.isAdmin ?? false;

    const [billingMode, setBillingMode] = useState<BillingMode>('package');
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [preview, setPreview] = useState<Preview | null>(null);
    const [previewError, setPreviewError] = useState<string | null>(null);
    const [discountPercent, setDiscountPercent] = useState('0');
    const [payments, setPayments] = useState<PurchasePaymentInput[]>([createEmptyPurchasePayment()]);

    const parsedDiscount = parseFloat(discountPercent) || 0;
    const calculatedTotal = preview?.calculated_price ?? 0;
    const finalTotal = preview ? applyPurchaseDiscount(calculatedTotal, parsedDiscount) : 0;
    const paymentsTotal = purchasePaymentsTotal(payments);
    const maxDiscount = preview
        ? maxPurchaseDiscountPercent(
              isAdmin,
              calculatedTotal,
              validPaymentsForPreview(payments),
              parsedDiscount,
              discountRules,
          )
        : isAdmin
          ? 100
          : discountRules.staffMaxCashPixDiscount;
    const discountExceedsLimit = parsedDiscount > maxDiscount + 0.001;
    const paymentDifference = finalTotal - paymentsTotal;
    const paymentRemaining = paymentDifference > 0.01 ? paymentDifference : 0;
    const paymentSurplus = paymentDifference < -0.01 ? -paymentDifference : 0;
    const paymentBalanced = Math.abs(paymentDifference) <= 0.01 && finalTotal > 0;
    const paymentsMismatch =
        preview !== null && !paymentBalanced && (paymentsTotal > 0 || paymentRemaining > 0);

    const toggleTreatment = (id: number) => {
        setSelectedIds((prev) =>
            prev.includes(id) ? prev.filter((t) => t !== id) : [...prev, id],
        );
    };

    const updatePayment = (index: number, patch: Partial<PurchasePaymentInput>) => {
        setPayments((prev) =>
            prev.map((payment, paymentIndex) =>
                paymentIndex === index ? { ...payment, ...patch } : payment,
            ),
        );
    };

    const addPayment = () => {
        setPayments((prev) => [...prev, createEmptyPurchasePayment()]);
    };

    const removePayment = (index: number) => {
        setPayments((prev) => (prev.length === 1 ? prev : prev.filter((_, i) => i !== index)));
    };

    const fetchPreview = useCallback(async () => {
        if (selectedIds.length === 0) {
            setPreview(null);
            setPreviewError(null);

            return;
        }

        const csrf =
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

        const response = await fetch(routes.clientTreatments.preview(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({
                billing_mode: billingMode,
                treatment_ids: selectedIds,
                purchased_at: todayDateKey(),
                discount_percent: parsedDiscount,
                payments: validPaymentsForPreview(payments).map((payment) => ({
                    method: payment.method,
                    amount: parseFloat(payment.amount),
                    installments:
                        payment.method === 'card'
                            ? payment.card_type === 'debit'
                                ? 1
                                : parseInt(payment.installments, 10) || 1
                            : null,
                    card_type: payment.method === 'card' ? payment.card_type : null,
                })),
            }),
        });

        if (!response.ok) {
            const data = await response.json();
            setPreview(null);
            setPreviewError(data.message ?? 'Não foi possível calcular o valor.');

            return;
        }

        const data = await response.json();
        setPreview(data);
        setPreviewError(null);
    }, [billingMode, parsedDiscount, payments, selectedIds]);

    useEffect(() => {
        const timer = setTimeout(fetchPreview, 300);

        return () => clearTimeout(timer);
    }, [fetchPreview]);

    const discountHint = useMemo(() => {
        if (isAdmin) {
            return 'Como administrador, o desconto não possui limite.';
        }

        return `Profissional: até ${discountRules.staffMaxCardDiscount}% no cartão ou até ${discountRules.staffMaxCashPixDiscount}% em dinheiro/Pix. Pagamento misto com pelo menos ${discountRules.mixedPaymentCashPixThreshold}% em dinheiro/Pix permite manter ${discountRules.staffMaxCashPixDiscount}%.`;
    }, [discountRules, isAdmin]);

    const packageSessions = billingMode === 'package_6' ? 6 : billingMode === 'package' ? 10 : null;
    const combo =
        packageSessions !== null
            ? combos.find((item) => item.sessions_count === packageSessions) ?? null
            : null;
    const comboMinCount = combo?.min_treatment_count ?? 2;
    const isCombo =
        packageSessions !== null && selectedIds.length >= comboMinCount;

    return (
        <>
            <Head title="Orçamentos" />
            <div className="page-container mx-auto w-full max-w-2xl">
                <PageHeader
                    title="Orçamentos"
                    description="Simule valores, desconto e formas de pagamento sem atribuir a um cliente"
                />

                <div className="space-y-6">
                    <div className="grid gap-2">
                        <Label>Modalidade</Label>
                        <div className="flex flex-wrap gap-4">
                            <label className="flex items-center gap-2">
                                <input
                                    type="radio"
                                    name="billing_mode"
                                    value="single"
                                    checked={billingMode === 'single'}
                                    onChange={() => setBillingMode('single')}
                                />
                                Sessão avulsa
                            </label>
                            <label className="flex items-center gap-2">
                                <input
                                    type="radio"
                                    name="billing_mode"
                                    value="package_6"
                                    checked={billingMode === 'package_6'}
                                    onChange={() => setBillingMode('package_6')}
                                />
                                Pacote 6 sessões
                            </label>
                            <label className="flex items-center gap-2">
                                <input
                                    type="radio"
                                    name="billing_mode"
                                    value="package"
                                    checked={billingMode === 'package'}
                                    onChange={() => setBillingMode('package')}
                                />
                                Pacote 10 sessões
                            </label>
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label>Tratamentos</Label>
                        <TreatmentCatalogSelect
                            treatments={treatments}
                            selectedIds={selectedIds}
                            onToggle={toggleTreatment}
                            billingMode={billingMode}
                            emptyMessage="Cadastre tratamentos em Tratamentos antes de simular orçamentos."
                        />
                        {isCombo && combo && (
                            <p className="text-muted-foreground text-sm">
                                Combo pacote {combo.sessions_count} sessões (a partir do 2º
                                tratamento): o mais caro sem desconto adicional; os demais com{' '}
                                {parseFloat(combo.extra_discount_percent)}% de desconto.
                            </p>
                        )}
                    </div>

                    {previewError && (
                        <p className="text-destructive text-sm">{previewError}</p>
                    )}

                    {preview && (
                        <PurchaseDiscountValidation
                            preview={preview}
                            calculatedTotal={calculatedTotal}
                            finalTotal={finalTotal}
                            parsedDiscount={parsedDiscount}
                            discountPercent={discountPercent}
                            onDiscountPercentChange={setDiscountPercent}
                            isAdmin={isAdmin}
                            maxDiscount={maxDiscount}
                            discountHint={discountHint}
                            discountExceedsLimit={discountExceedsLimit}
                            payments={payments}
                            onUpdatePayment={updatePayment}
                            onAddPayment={addPayment}
                            onRemovePayment={removePayment}
                            paymentRemaining={paymentRemaining}
                            paymentSurplus={paymentSurplus}
                            paymentBalanced={paymentBalanced}
                            paymentsMismatch={paymentsMismatch}
                            paymentsTotal={paymentsTotal}
                            paymentLabel="Formas de pagamento (simulação)"
                        />
                    )}

                    {selectedIds.length === 0 && treatments.length > 0 && (
                        <p className="text-muted-foreground text-sm">
                            Selecione um ou mais tratamentos para simular o orçamento.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

QuotesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Orçamentos', href: routes.quotes.index() },
    ],
};
