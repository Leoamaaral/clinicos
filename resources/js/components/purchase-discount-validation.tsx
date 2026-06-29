import { Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    formatCurrency,
    purchasePaymentMethodLabels,
    type PurchasePaymentInput,
} from '@/lib/clinic';

const cardTypeLabels: Record<PurchasePaymentInput['card_type'], string> = {
    debit: 'Débito',
    credit: 'Crédito',
};

type PreviewLine = {
    treatment_name: string;
    unit_price: number;
    sessions_total: number;
    combo_no_discount: boolean;
};

type Preview = {
    purchase_type_label: string;
    lines: PreviewLine[];
    is_combo: boolean;
};

type Props = {
    preview: Preview;
    calculatedTotal: number;
    finalTotal: number;
    parsedDiscount: number;
    discountPercent: string;
    onDiscountPercentChange: (value: string) => void;
    isAdmin: boolean;
    maxDiscount: number;
    discountHint: string;
    discountExceedsLimit: boolean;
    payments: PurchasePaymentInput[];
    onUpdatePayment: (index: number, patch: Partial<PurchasePaymentInput>) => void;
    onAddPayment: () => void;
    onRemovePayment: (index: number) => void;
    paymentRemaining: number;
    paymentSurplus: number;
    paymentBalanced: boolean;
    paymentsMismatch: boolean;
    paymentsTotal: number;
    includeFormFields?: boolean;
    discountError?: string;
    paymentsError?: string;
    paymentLabel?: string;
    hidePricing?: boolean;
};

export function PurchaseDiscountValidation({
    preview,
    calculatedTotal,
    finalTotal,
    parsedDiscount,
    discountPercent,
    onDiscountPercentChange,
    isAdmin,
    maxDiscount,
    discountHint,
    discountExceedsLimit,
    payments,
    onUpdatePayment,
    onAddPayment,
    onRemovePayment,
    paymentRemaining,
    paymentSurplus,
    paymentBalanced,
    paymentsMismatch,
    paymentsTotal,
    includeFormFields = false,
    discountError,
    paymentsError,
    paymentLabel = 'Formas de pagamento *',
    hidePricing = false,
}: Props) {
    const isValid =
        !discountExceedsLimit &&
        paymentBalanced &&
        payments.every(
            (payment) =>
                parseFloat(payment.amount) > 0 &&
                (payment.method !== 'card' ||
                    (payment.card_type === 'debit' ||
                        (payment.card_type === 'credit' &&
                            parseInt(payment.installments, 10) >= 1 &&
                            parseInt(payment.installments, 10) <= 10))),
        );

    return (
        <>
            <div className="space-y-4 rounded-lg border bg-muted/30 p-4">
                <div>
                    <p className="font-medium">{preview.purchase_type_label}</p>
                    <ul className="mt-2 space-y-1 text-sm">
                        {preview.lines.map((line) => (
                            <li key={line.treatment_name} className="flex justify-between gap-2">
                                <span>
                                    {line.treatment_name}
                                    {line.sessions_total > 1 && ` (${line.sessions_total} sessões)`}
                                    {line.combo_no_discount &&
                                        preview.is_combo &&
                                        ' — sem desconto combo'}
                                    {!line.combo_no_discount &&
                                        preview.is_combo &&
                                        ' — com desconto combo'}
                                </span>
                                <span>{formatCurrency(line.unit_price)}</span>
                            </li>
                        ))}
                    </ul>
                </div>

                {hidePricing ? (
                    <div className="space-y-1 border-t pt-4 text-sm">
                        <div className="flex justify-between text-muted-foreground">
                            <span>Valor de referência</span>
                            <span>{formatCurrency(calculatedTotal)}</span>
                        </div>
                        <p className="text-lg font-semibold text-teal-800 dark:text-teal-300">
                            Cortesia — {formatCurrency(0)}
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="grid gap-2 border-t pt-4">
                            <Label htmlFor="discount_percent">Desconto (%)</Label>
                            <Input
                                id="discount_percent"
                                name={includeFormFields ? 'discount_percent' : undefined}
                                type="number"
                                min="0"
                                max={isAdmin ? '100' : String(maxDiscount)}
                                step="0.01"
                                value={discountPercent}
                                onChange={(event) => onDiscountPercentChange(event.target.value)}
                            />
                            <p className="text-muted-foreground text-xs">{discountHint}</p>
                            {!isAdmin && (
                                <p className="text-muted-foreground text-xs">
                                    Desconto máximo atual: {maxDiscount.toFixed(2).replace('.', ',')}%
                                </p>
                            )}
                            {discountExceedsLimit && (
                                <p className="text-destructive text-sm">
                                    Desconto acima do permitido para esta forma de pagamento.
                                </p>
                            )}
                            {discountError && <InputError message={discountError} />}
                        </div>

                        <div className="space-y-1 text-sm">
                            <div className="flex justify-between">
                                <span>Subtotal calculado</span>
                                <span>{formatCurrency(calculatedTotal)}</span>
                            </div>
                            {parsedDiscount > 0 && (
                                <div className="text-muted-foreground flex justify-between">
                                    <span>Desconto ({parsedDiscount}%)</span>
                                    <span>− {formatCurrency(calculatedTotal - finalTotal)}</span>
                                </div>
                            )}
                            <p className="pt-1 text-lg font-semibold">
                                Total: {formatCurrency(finalTotal)}
                            </p>
                        </div>
                    </>
                )}
            </div>

            {!hidePricing && (
            <div className="grid gap-3">
                <div className="flex items-center justify-between gap-2">
                    <Label>{paymentLabel}</Label>
                    <Button type="button" variant="outline" size="sm" onClick={onAddPayment}>
                        <Plus className="size-4" />
                        Adicionar
                    </Button>
                </div>
                <div className="space-y-3">
                    {payments.map((payment, index) => (
                        <div
                            key={index}
                            className="grid gap-3 rounded-lg border p-3 sm:grid-cols-[1fr_120px_100px_100px_auto]"
                        >
                            <div className="grid gap-1">
                                <Label className="text-xs">Método</Label>
                                <select
                                    name={
                                        includeFormFields ? `payments[${index}][method]` : undefined
                                    }
                                    value={payment.method}
                                    onChange={(event) => {
                                        const method = event.target
                                            .value as PurchasePaymentInput['method'];

                                        onUpdatePayment(index, {
                                            method,
                                            ...(method === 'card'
                                                ? {
                                                      card_type: 'credit' as const,
                                                      installments: '1',
                                                  }
                                                : {}),
                                        });
                                    }}
                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                >
                                    {Object.entries(purchasePaymentMethodLabels).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </div>
                            <div className="grid gap-1">
                                <Label className="text-xs">Valor (R$)</Label>
                                <Input
                                    name={
                                        includeFormFields ? `payments[${index}][amount]` : undefined
                                    }
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    value={payment.amount}
                                    onChange={(event) =>
                                        onUpdatePayment(index, { amount: event.target.value })
                                    }
                                    placeholder="0,00"
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label className="text-xs">Tipo</Label>
                                {payment.method === 'card' ? (
                                    <select
                                        name={
                                            includeFormFields
                                                ? `payments[${index}][card_type]`
                                                : undefined
                                        }
                                        value={payment.card_type}
                                        onChange={(event) => {
                                            const card_type = event.target
                                                .value as PurchasePaymentInput['card_type'];

                                            onUpdatePayment(index, {
                                                card_type,
                                                installments:
                                                    card_type === 'debit' ? '1' : payment.installments,
                                            });
                                        }}
                                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                    >
                                        {Object.entries(cardTypeLabels).map(([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <div className="text-muted-foreground flex h-9 items-center text-xs">
                                        —
                                    </div>
                                )}
                            </div>
                            <div className="grid gap-1">
                                <Label className="text-xs">Parcelas</Label>
                                {payment.method === 'card' &&
                                payment.card_type === 'credit' ? (
                                    <select
                                        name={
                                            includeFormFields
                                                ? `payments[${index}][installments]`
                                                : undefined
                                        }
                                        value={payment.installments}
                                        onChange={(event) =>
                                            onUpdatePayment(index, {
                                                installments: event.target.value,
                                            })
                                        }
                                        className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                    >
                                        {Array.from({ length: 10 }, (_, i) => i + 1).map(
                                            (installment) => (
                                                <option key={installment} value={installment}>
                                                    {installment}x
                                                </option>
                                            ),
                                        )}
                                    </select>
                                ) : (
                                    <div className="text-muted-foreground flex h-9 items-center text-xs">
                                        {payment.method === 'card' ? (
                                            <>
                                                1x
                                                {includeFormFields && (
                                                    <input
                                                        type="hidden"
                                                        name={`payments[${index}][installments]`}
                                                        value="1"
                                                    />
                                                )}
                                            </>
                                        ) : (
                                            '—'
                                        )}
                                    </div>
                                )}
                            </div>
                            <div className="flex items-end">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="text-destructive hover:text-destructive"
                                    disabled={payments.length === 1}
                                    onClick={() => onRemovePayment(index)}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
                <div className="space-y-1 rounded-lg border bg-muted/20 p-3 text-sm">
                    <div className="flex justify-between gap-2">
                        <span className="text-muted-foreground">Total a pagar</span>
                        <span className="font-medium">{formatCurrency(finalTotal)}</span>
                    </div>
                    <div className="flex justify-between gap-2">
                        <span className="text-muted-foreground">Total informado</span>
                        <span
                            className={
                                paymentsMismatch && paymentsTotal > 0
                                    ? 'text-destructive font-medium'
                                    : 'font-medium'
                            }
                        >
                            {formatCurrency(paymentsTotal)}
                        </span>
                    </div>
                    {paymentRemaining > 0 && (
                        <div className="flex justify-between gap-2 border-t pt-2 font-semibold text-amber-800 dark:text-amber-300">
                            <span>Falta</span>
                            <span>{formatCurrency(paymentRemaining)}</span>
                        </div>
                    )}
                    {paymentSurplus > 0 && (
                        <div className="flex justify-between gap-2 border-t pt-2 font-semibold text-destructive">
                            <span>Excedente</span>
                            <span>{formatCurrency(paymentSurplus)}</span>
                        </div>
                    )}
                    {paymentBalanced && (
                        <div className="flex justify-between gap-2 border-t pt-2 font-medium text-teal-800 dark:text-teal-300">
                            <span>Conta fechada</span>
                            <span>✓</span>
                        </div>
                    )}
                </div>
                {paymentsMismatch && paymentRemaining > 0 && (
                    <p className="text-muted-foreground text-sm">
                        Informe mais {formatCurrency(paymentRemaining)} para fechar a conta.
                    </p>
                )}
                {paymentsMismatch && paymentSurplus > 0 && (
                    <p className="text-destructive text-sm">
                        O total informado excede o valor com desconto em{' '}
                        {formatCurrency(paymentSurplus)}.
                    </p>
                )}
                {paymentsError && <InputError message={paymentsError} />}
                {!includeFormFields && paymentsTotal > 0 && (
                    <div
                        className={
                            isValid
                                ? 'rounded-lg border border-teal-200 bg-teal-50 p-3 text-sm text-teal-900 dark:border-teal-900 dark:bg-teal-950/40 dark:text-teal-200'
                                : 'rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
                        }
                    >
                        {isValid
                            ? 'Desconto e pagamento válidos para atribuição ao cliente.'
                            : 'Este orçamento não poderia ser confirmado com estas condições.'}
                    </div>
                )}
            </div>
            )}
        </>
    );
}
