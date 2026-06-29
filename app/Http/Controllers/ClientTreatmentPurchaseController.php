<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientTreatmentPurchaseRequest;
use App\Models\AppointmentTreatment;
use App\Models\Client;
use App\Models\ClientTreatmentPurchase;
use App\Models\ClientTreatmentPurchasePayment;
use App\Models\Treatment;
use App\Models\TreatmentCombo;
use App\Services\PurchaseDiscountService;
use App\Services\TreatmentPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ClientTreatmentPurchaseController extends Controller
{
    public function __construct(
        private TreatmentPricingService $pricingService,
        private PurchaseDiscountService $discountService,
    ) {}

    public function create(Client $client): Response
    {
        return Inertia::render('clients/treatments/create', [
            'client' => $client,
            'treatments' => Treatment::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'single_price', 'package_price', 'package_6_price']),
            'combos' => TreatmentCombo::query()
                ->where('is_active', true)
                ->orderBy('sessions_count')
                ->get(['sessions_count', 'min_treatment_count', 'extra_discount_percent']),
            'discountRules' => [
                'staffMaxCardDiscount' => PurchaseDiscountService::STAFF_MAX_CARD_DISCOUNT,
                'staffMaxCashPixDiscount' => PurchaseDiscountService::STAFF_MAX_CASH_PIX_DISCOUNT,
                'mixedPaymentCashPixThreshold' => PurchaseDiscountService::MIXED_PAYMENT_CASH_PIX_THRESHOLD,
            ],
        ]);
    }

    public function store(StoreClientTreatmentPurchaseRequest $request, Client $client): RedirectResponse
    {
        $treatmentIds = array_values(array_unique($request->input('treatment_ids')));

        $billingMode = $request->string('billing_mode')->toString();
        $isCourtesy = $request->boolean('is_courtesy');
        $discountPercent = $isCourtesy ? 0.0 : (float) ($request->input('discount_percent') ?? 0);
        $payments = $isCourtesy
            ? []
            : collect($request->input('payments', []))
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

        try {
            $pricing = $this->pricingService->calculate(
                $treatmentIds,
                $billingMode,
                $this->comboForBillingMode($billingMode),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['treatment_ids' => $e->getMessage()]);
        }

        $calculatedTotal = (float) $pricing['total_price'];
        $finalTotal = $isCourtesy
            ? 0.0
            : $this->discountService->applyDiscount($calculatedTotal, $discountPercent);

        DB::transaction(function () use ($client, $request, $pricing, $calculatedTotal, $discountPercent, $finalTotal, $payments, $isCourtesy) {
            $purchase = ClientTreatmentPurchase::create([
                'client_id' => $client->id,
                'purchase_type' => $pricing['purchase_type'],
                'calculated_price' => $calculatedTotal,
                'discount_percent' => $discountPercent,
                'total_price' => $finalTotal,
                'is_courtesy' => $isCourtesy,
                'purchased_at' => $request->date('purchased_at'),
                'notes' => $request->input('notes'),
            ]);

            foreach ($pricing['items'] as $item) {
                $purchase->items()->create($item);
            }

            foreach ($payments as $payment) {
                $purchase->payments()->create([
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'installments' => $payment['method'] === ClientTreatmentPurchasePayment::METHOD_CARD
                        ? ($payment['card_type'] === ClientTreatmentPurchasePayment::CARD_TYPE_DEBIT
                            ? 1
                            : $payment['installments'])
                        : null,
                    'card_type' => $payment['method'] === ClientTreatmentPurchasePayment::METHOD_CARD
                        ? $payment['card_type']
                        : null,
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tratamentos atribuídos ao cliente com sucesso.']);

        return to_route('clients.show', $client);
    }

    public function preview(StoreClientTreatmentPurchaseRequest $request): JsonResponse
    {
        $treatmentIds = array_values(array_unique($request->input('treatment_ids')));

        $billingMode = $request->string('billing_mode')->toString();
        $isCourtesy = $request->boolean('is_courtesy');
        $discountPercent = $isCourtesy ? 0.0 : (float) ($request->input('discount_percent') ?? 0);
        $payments = $isCourtesy
            ? []
            : collect($request->input('payments', []))
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

        try {
            $pricing = $this->pricingService->calculate(
                $treatmentIds,
                $billingMode,
                $this->comboForBillingMode($billingMode),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $treatments = Treatment::query()
            ->whereIn('id', $treatmentIds)
            ->get()
            ->keyBy('id');

        $packageSessions = TreatmentPricingService::isPackageBillingMode($billingMode)
            ? TreatmentPricingService::packageSessionsForBillingMode($billingMode)
            : null;

        $lines = collect($pricing['items'])->map(function (array $item) use ($treatments, $packageSessions) {
            $treatment = $treatments[$item['treatment_id']];
            $basePrice = (float) ($packageSessions !== null
                ? $treatment->packagePriceForSessions($packageSessions)
                : $treatment->single_price);

            return [
                'treatment_name' => $treatment->name,
                'base_price' => $basePrice,
                'unit_price' => $item['unit_price'],
                'sessions_total' => $item['sessions_total'],
                'combo_no_discount' => $item['combo_no_discount'],
            ];
        });

        $calculatedTotal = (float) $pricing['total_price'];
        $finalTotal = $isCourtesy
            ? 0.0
            : $this->discountService->applyDiscount($calculatedTotal, $discountPercent);
        $user = $request->user();
        $maxDiscountPercent = $user
            ? $this->discountService->maxDiscountPercent($user, $calculatedTotal, $payments, $discountPercent)
            : PurchaseDiscountService::STAFF_MAX_CASH_PIX_DISCOUNT;

        return response()->json([
            'purchase_type' => $pricing['purchase_type'],
            'purchase_type_label' => ClientTreatmentPurchase::typeLabels()[$pricing['purchase_type']],
            'calculated_price' => $calculatedTotal,
            'discount_percent' => $discountPercent,
            'total_price' => $finalTotal,
            'max_discount_percent' => $maxDiscountPercent,
            'is_courtesy' => $isCourtesy,
            'lines' => $lines,
            'is_combo' => TreatmentPricingService::isComboPurchase(
                $billingMode,
                count($treatmentIds),
                $this->comboForBillingMode($billingMode),
            ),
        ]);
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

    public function destroy(Client $client, ClientTreatmentPurchase $purchase): RedirectResponse
    {
        if ($purchase->client_id !== $client->id) {
            abort(404);
        }

        $purchase->load('items');

        if ($reason = $purchase->deletionBlockReason()) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => $reason]);

            return to_route('clients.show', $client);
        }

        DB::transaction(function () use ($purchase) {
            $itemIds = $purchase->items->pluck('id');

            AppointmentTreatment::query()
                ->whereIn('client_treatment_purchase_item_id', $itemIds)
                ->update(['client_treatment_purchase_item_id' => null]);

            $purchase->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Compra de tratamentos removida com sucesso.']);

        return to_route('clients.show', $client);
    }
}
