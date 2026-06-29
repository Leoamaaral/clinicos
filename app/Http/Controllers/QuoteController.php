<?php

namespace App\Http\Controllers;

use App\Models\Treatment;
use App\Models\TreatmentCombo;
use App\Services\PurchaseDiscountService;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('quotes/index', [
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
}
