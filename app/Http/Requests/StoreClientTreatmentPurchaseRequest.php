<?php

namespace App\Http\Requests;

use App\Models\ClientTreatmentPurchasePayment;
use App\Models\TreatmentCombo;
use App\Services\PurchaseDiscountService;
use App\Services\TreatmentPricingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class StoreClientTreatmentPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isStore = $this->routeIs('clients.treatments.store');
        $isCourtesy = $this->boolean('is_courtesy');

        return [
            'billing_mode' => ['required', Rule::in(['single', 'package_6', 'package'])],
            'treatment_ids' => ['required', 'array', 'min:1'],
            'treatment_ids.*' => ['integer', 'exists:treatments,id'],
            'purchased_at' => [$isStore ? 'required' : 'nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_courtesy' => ['nullable', 'boolean'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payments' => array_values(array_filter([
                $isStore && ! $isCourtesy ? 'required' : 'nullable',
                'array',
                $isStore && ! $isCourtesy ? 'min:1' : null,
            ])),
            'payments.*.method' => [
                'required_with:payments',
                Rule::in([
                    ClientTreatmentPurchasePayment::METHOD_CASH,
                    ClientTreatmentPurchasePayment::METHOD_PIX,
                    ClientTreatmentPurchasePayment::METHOD_CARD,
                ]),
            ],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
            'payments.*.installments' => ['nullable', 'integer', 'min:1', 'max:10'],
            'payments.*.card_type' => [
                'nullable',
                Rule::in([
                    ClientTreatmentPurchasePayment::CARD_TYPE_DEBIT,
                    ClientTreatmentPurchasePayment::CARD_TYPE_CREDIT,
                ]),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            if ($user === null) {
                return;
            }

            /** @var PurchaseDiscountService $discountService */
            $discountService = app(PurchaseDiscountService::class);
            /** @var TreatmentPricingService $pricingService */
            $pricingService = app(TreatmentPricingService::class);

            $treatmentIds = array_values(array_unique($this->input('treatment_ids', [])));
            $billingMode = $this->string('billing_mode')->toString();
            $discountPercent = (float) ($this->input('discount_percent') ?? 0);
            $payments = collect($this->input('payments', []))
                ->map(fn (array $payment) => [
                    'method' => $payment['method'],
                    'amount' => (float) $payment['amount'],
                    'installments' => ! empty($payment['installments'])
                        ? (int) $payment['installments']
                        : null,
                    'card_type' => ! empty($payment['card_type'])
                        ? $payment['card_type']
                        : null,
                ])
                ->values()
                ->all();

            foreach ($payments as $index => $payment) {
                if (
                    $this->routeIs('clients.treatments.store')
                    && $payment['method'] === ClientTreatmentPurchasePayment::METHOD_CARD
                    && $payment['card_type'] === null
                ) {
                    $validator->errors()->add(
                        "payments.{$index}.card_type",
                        'Informe se o cartão é débito ou crédito.',
                    );
                }

                if (
                    $this->routeIs('clients.treatments.store')
                    && $payment['method'] === ClientTreatmentPurchasePayment::METHOD_CARD
                    && $payment['card_type'] === ClientTreatmentPurchasePayment::CARD_TYPE_DEBIT
                    && $payment['installments'] !== null
                    && $payment['installments'] !== 1
                ) {
                    $validator->errors()->add(
                        "payments.{$index}.installments",
                        'Pagamento no débito deve ser em parcela única.',
                    );
                }

                if (
                    $this->routeIs('clients.treatments.store')
                    && $payment['method'] === ClientTreatmentPurchasePayment::METHOD_CARD
                    && $payment['card_type'] === ClientTreatmentPurchasePayment::CARD_TYPE_CREDIT
                    && $payment['installments'] === null
                ) {
                    $validator->errors()->add(
                        "payments.{$index}.installments",
                        'Informe o número de parcelas para pagamento no cartão.',
                    );
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                $pricing = $pricingService->calculate(
                    $treatmentIds,
                    $billingMode,
                    $this->comboForBillingMode($billingMode),
                );
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('treatment_ids', $e->getMessage());

                return;
            }

            $calculatedTotal = (float) $pricing['total_price'];

            if ($this->boolean('is_courtesy')) {
                return;
            }

            $finalTotal = $discountService->applyDiscount($calculatedTotal, $discountPercent);
            $maxDiscount = $discountService->maxDiscountPercent(
                $user,
                $calculatedTotal,
                $payments,
                $discountPercent,
            );

            if ($discountPercent > $maxDiscount) {
                $validator->errors()->add(
                    'discount_percent',
                    $user->isAdmin()
                        ? 'Desconto inválido.'
                        : sprintf(
                            'Desconto máximo permitido: %s%% para esta forma de pagamento.',
                            number_format($maxDiscount, 2, ',', '.'),
                        ),
                );
            }

            if (! $this->routeIs('clients.treatments.store') || $payments === []) {
                return;
            }

            $paymentsTotal = $discountService->paymentsTotal($payments);

            if (abs($paymentsTotal - $finalTotal) > 0.01) {
                $validator->errors()->add(
                    'payments',
                    'A soma dos pagamentos deve ser igual ao valor final com desconto.',
                );
            }
        });
    }

    private function comboForBillingMode(string $billingMode): ?TreatmentCombo
    {
        if (! TreatmentPricingService::isPackageBillingMode($billingMode)) {
            return null;
        }

        return TreatmentCombo::activeForSessions(
            TreatmentPricingService::packageSessionsForBillingMode($billingMode),
        );
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'treatment_ids.required' => 'Selecione ao menos um tratamento.',
            'treatment_ids.min' => 'Selecione ao menos um tratamento.',
            'payments.required' => 'Informe ao menos uma forma de pagamento.',
            'payments.min' => 'Informe ao menos uma forma de pagamento.',
        ];
    }
}
