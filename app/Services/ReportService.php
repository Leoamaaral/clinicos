<?php

namespace App\Services;

use App\Models\AnamnesisAnswer;
use App\Models\AnamnesisInvitation;
use App\Models\AnamnesisQuestion;
use App\Models\AnamnesisRecord;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientTreatmentPurchase;
use App\Models\ClientTreatmentPurchaseItem;
use App\Models\ClientTreatmentPurchasePayment;
use App\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    private const INACTIVE_CLIENT_DAYS = 60;

    public function __construct(
        private CardTerminalFeeService $cardTerminalFeeService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        $periodDays = $start->diffInDays($end) + 1;

        $previousStart = $start->copy()->subDays($periodDays);
        $previousEnd = $start->copy()->subDay()->endOfDay();

        $currentRevenue = $this->totalRevenue($start, $end);
        $previousRevenue = $this->totalRevenue($previousStart, $previousEnd);
        $paidPurchaseCount = $this->paidPurchaseCount($start, $end);
        $courtesyCount = $this->courtesyPurchaseCount($start, $end);
        $courtesyReferenceValue = $this->courtesyReferenceValue($start, $end);
        $courtesySessions = $this->courtesySessionsInPeriod($start, $end);

        return [
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'summary' => [
                'revenue' => round($currentRevenue, 2),
                'purchase_count' => $paidPurchaseCount,
                'paid_purchase_count' => $paidPurchaseCount,
                'courtesy_count' => $courtesyCount,
                'courtesy_reference_value' => round($courtesyReferenceValue, 2),
                'courtesy_sessions' => $courtesySessions,
                'average_ticket' => $paidPurchaseCount > 0
                    ? round($currentRevenue / $paidPurchaseCount, 2)
                    : 0.0,
                'previous_revenue' => round($previousRevenue, 2),
                'revenue_change_percent' => $this->percentChange($previousRevenue, $currentRevenue),
            ],
            'revenue_chart' => $this->revenueChart($start, $end),
            'top_treatments' => $this->topTreatments($start, $end),
            'top_clients' => $this->topClients($start, $end),
            'sales_mix' => $this->salesMix($start, $end),
            'payment_summary' => $this->paymentSummary($start, $end),
            'appointment_status' => $this->appointmentStatus($start, $end),
            'professional_productivity' => $this->professionalProductivity($start, $end),
            'inactive_clients' => $this->inactiveClientsWithSessions(),
            'peak_hours' => $this->peakHours($start, $end),
            'anamnesis' => $this->anamnesisReport($start, $end),
            'notifications' => $this->notificationsReport($start, $end),
            'unused_sessions' => $this->unusedSessions(),
        ];
    }

    /** @return Builder<ClientTreatmentPurchase> */
    private function purchasesInPeriod(Carbon $start, Carbon $end): Builder
    {
        return ClientTreatmentPurchase::query()
            ->whereDate('purchased_at', '>=', $start->toDateString())
            ->whereDate('purchased_at', '<=', $end->toDateString());
    }

    /** @return Builder<ClientTreatmentPurchase> */
    private function paidPurchasesInPeriod(Carbon $start, Carbon $end): Builder
    {
        return $this->purchasesInPeriod($start, $end)->where('is_courtesy', false);
    }

    /** @return Builder<ClientTreatmentPurchase> */
    private function courtesyPurchasesInPeriod(Carbon $start, Carbon $end): Builder
    {
        return $this->purchasesInPeriod($start, $end)->where('is_courtesy', true);
    }

    private function totalRevenue(Carbon $start, Carbon $end): float
    {
        return (float) $this->purchasesInPeriod($start, $end)->sum('total_price');
    }

    private function paidPurchaseCount(Carbon $start, Carbon $end): int
    {
        return $this->paidPurchasesInPeriod($start, $end)->count();
    }

    private function courtesyPurchaseCount(Carbon $start, Carbon $end): int
    {
        return $this->courtesyPurchasesInPeriod($start, $end)->count();
    }

    private function courtesyReferenceValue(Carbon $start, Carbon $end): float
    {
        return (float) $this->courtesyPurchasesInPeriod($start, $end)->sum('calculated_price');
    }

    private function courtesySessionsInPeriod(Carbon $start, Carbon $end): int
    {
        return (int) ClientTreatmentPurchaseItem::query()
            ->join('client_treatment_purchases as purchases', 'purchases.id', '=', 'client_treatment_purchase_items.client_treatment_purchase_id')
            ->where('purchases.is_courtesy', true)
            ->whereDate('purchases.purchased_at', '>=', $start->toDateString())
            ->whereDate('purchases.purchased_at', '<=', $end->toDateString())
            ->sum('client_treatment_purchase_items.sessions_total');
    }

    private function percentChange(float $previous, float $current): ?float
    {
        if ($previous == 0.0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @return array{granularity: string, labels: list<string>, revenue: list<float>, purchases: list<int>}
     */
    private function revenueChart(Carbon $start, Carbon $end): array
    {
        $periodDays = $start->diffInDays($end) + 1;

        if ($periodDays <= 31) {
            return $this->dailyRevenueChart($start, $end);
        }

        if ($periodDays <= 90) {
            return $this->weeklyRevenueChart($start, $end);
        }

        return $this->monthlyRevenueChart($start, $end);
    }

    /**
     * @return array{granularity: string, labels: list<string>, revenue: list<float>, purchases: list<int>, courtesies: list<int>}
     */
    private function dailyRevenueChart(Carbon $start, Carbon $end): array
    {
        $rows = $this->purchasesInPeriod($start, $end)
            ->selectRaw('purchased_at as period')
            ->selectRaw('SUM(total_price) as revenue')
            ->selectRaw('SUM(CASE WHEN is_courtesy = 0 THEN 1 ELSE 0 END) as purchases')
            ->selectRaw('SUM(CASE WHEN is_courtesy = 1 THEN 1 ELSE 0 END) as courtesies')
            ->groupBy('purchased_at')
            ->orderBy('purchased_at')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->period)->toDateString());

        $labels = [];
        $revenue = [];
        $purchases = [];
        $courtesies = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $row = $rows->get($key);

            $labels[] = $date->format('d/m');
            $revenue[] = round((float) ($row->revenue ?? 0), 2);
            $purchases[] = (int) ($row->purchases ?? 0);
            $courtesies[] = (int) ($row->courtesies ?? 0);
        }

        return [
            'granularity' => 'daily',
            'labels' => $labels,
            'revenue' => $revenue,
            'purchases' => $purchases,
            'courtesies' => $courtesies,
        ];
    }

    /**
     * @return array{granularity: string, labels: list<string>, revenue: list<float>, purchases: list<int>, courtesies: list<int>}
     */
    private function weeklyRevenueChart(Carbon $start, Carbon $end): array
    {
        $purchases = $this->purchasesInPeriod($start, $end)
            ->get(['purchased_at', 'total_price', 'is_courtesy']);

        $grouped = [];

        foreach ($purchases as $purchase) {
            $weekStart = Carbon::parse($purchase->purchased_at)->startOfWeek(Carbon::MONDAY);
            $key = $weekStart->toDateString();

            $grouped[$key] ??= ['revenue' => 0.0, 'purchases' => 0, 'courtesies' => 0];
            $grouped[$key]['revenue'] += (float) $purchase->total_price;

            if ($purchase->is_courtesy) {
                $grouped[$key]['courtesies']++;
            } else {
                $grouped[$key]['purchases']++;
            }
        }

        $labels = [];
        $revenue = [];
        $purchaseCounts = [];
        $courtesyCounts = [];

        for ($cursor = $start->copy()->startOfWeek(Carbon::MONDAY); $cursor->lte($end); $cursor->addWeek()) {
            $weekEnd = $cursor->copy()->endOfWeek(Carbon::SUNDAY);
            $key = $cursor->toDateString();
            $row = $grouped[$key] ?? ['revenue' => 0.0, 'purchases' => 0, 'courtesies' => 0];

            $labels[] = $cursor->format('d/m').' - '.$weekEnd->format('d/m');
            $revenue[] = round($row['revenue'], 2);
            $purchaseCounts[] = $row['purchases'];
            $courtesyCounts[] = $row['courtesies'];
        }

        return [
            'granularity' => 'weekly',
            'labels' => $labels,
            'revenue' => $revenue,
            'purchases' => $purchaseCounts,
            'courtesies' => $courtesyCounts,
        ];
    }

    /**
     * @return array{granularity: string, labels: list<string>, revenue: list<float>, purchases: list<int>, courtesies: list<int>}
     */
    private function monthlyRevenueChart(Carbon $start, Carbon $end): array
    {
        $driver = DB::connection()->getDriverName();

        $periodExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m', purchased_at)"
            : "DATE_FORMAT(purchased_at, '%Y-%m')";

        $rows = $this->purchasesInPeriod($start, $end)
            ->selectRaw("{$periodExpression} as period_key")
            ->selectRaw('SUM(total_price) as revenue')
            ->selectRaw('SUM(CASE WHEN is_courtesy = 0 THEN 1 ELSE 0 END) as purchases')
            ->selectRaw('SUM(CASE WHEN is_courtesy = 1 THEN 1 ELSE 0 END) as courtesies')
            ->groupBy('period_key')
            ->orderBy('period_key')
            ->get()
            ->keyBy('period_key');

        $labels = [];
        $revenue = [];
        $purchases = [];
        $courtesies = [];

        for ($date = $start->copy()->startOfMonth(); $date->lte($end); $date->addMonth()) {
            $key = $date->format('Y-m');
            $row = $rows->get($key);

            $labels[] = $date->translatedFormat('M/Y');
            $revenue[] = round((float) ($row->revenue ?? 0), 2);
            $purchases[] = (int) ($row->purchases ?? 0);
            $courtesies[] = (int) ($row->courtesies ?? 0);
        }

        return [
            'granularity' => 'monthly',
            'labels' => $labels,
            'revenue' => $revenue,
            'purchases' => $purchases,
            'courtesies' => $courtesies,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topTreatments(Carbon $start, Carbon $end, int $limit = 10): array
    {
        return ClientTreatmentPurchaseItem::query()
            ->join('client_treatment_purchases as purchases', 'purchases.id', '=', 'client_treatment_purchase_items.client_treatment_purchase_id')
            ->join('treatments', 'treatments.id', '=', 'client_treatment_purchase_items.treatment_id')
            ->whereDate('purchases.purchased_at', '>=', $start->toDateString())
            ->whereDate('purchases.purchased_at', '<=', $end->toDateString())
            ->groupBy('treatments.id', 'treatments.name')
            ->orderByDesc(DB::raw('SUM(CASE WHEN purchases.is_courtesy = 0 THEN client_treatment_purchase_items.unit_price ELSE 0 END)'))
            ->limit($limit)
            ->get([
                'treatments.id as treatment_id',
                'treatments.name as treatment_name',
                DB::raw('SUM(CASE WHEN purchases.is_courtesy = 0 THEN client_treatment_purchase_items.unit_price ELSE 0 END) as revenue'),
                DB::raw('SUM(client_treatment_purchase_items.sessions_total) as sessions_sold'),
                DB::raw('COUNT(*) as sales_count'),
            ])
            ->map(fn ($row) => [
                'treatment_id' => (int) $row->treatment_id,
                'treatment_name' => $row->treatment_name,
                'revenue' => round((float) $row->revenue, 2),
                'sessions_sold' => (int) $row->sessions_sold,
                'sales_count' => (int) $row->sales_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topClients(Carbon $start, Carbon $end, int $limit = 10): array
    {
        return ClientTreatmentPurchase::query()
            ->join('clients', 'clients.id', '=', 'client_treatment_purchases.client_id')
            ->whereDate('client_treatment_purchases.purchased_at', '>=', $start->toDateString())
            ->whereDate('client_treatment_purchases.purchased_at', '<=', $end->toDateString())
            ->groupBy('clients.id', 'clients.name')
            ->orderByDesc(DB::raw('SUM(client_treatment_purchases.total_price)'))
            ->limit($limit)
            ->get([
                'clients.id as client_id',
                'clients.name as client_name',
                DB::raw('SUM(client_treatment_purchases.total_price) as total_spent'),
                DB::raw('COUNT(*) as purchase_count'),
                DB::raw('MAX(client_treatment_purchases.purchased_at) as last_purchase'),
            ])
            ->map(fn ($row) => [
                'client_id' => (int) $row->client_id,
                'client_name' => $row->client_name,
                'total_spent' => round((float) $row->total_spent, 2),
                'purchase_count' => (int) $row->purchase_count,
                'average_ticket' => $row->purchase_count > 0
                    ? round((float) $row->total_spent / (int) $row->purchase_count, 2)
                    : 0.0,
                'last_purchase' => Carbon::parse($row->last_purchase)->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function salesMix(Carbon $start, Carbon $end): array
    {
        $rows = $this->purchasesInPeriod($start, $end)
            ->selectRaw('purchase_type, COUNT(*) as count, SUM(total_price) as revenue')
            ->groupBy('purchase_type')
            ->get();

        $totalRevenue = (float) $rows->sum('revenue');
        $totalCount = (int) $rows->sum('count');
        $typeLabels = ClientTreatmentPurchase::typeLabels();

        $items = $rows
            ->map(function ($row) use ($totalRevenue, $totalCount, $typeLabels) {
                $count = (int) $row->count;
                $revenue = (float) $row->revenue;

                return [
                    'purchase_type' => $row->purchase_type,
                    'label' => $typeLabels[$row->purchase_type] ?? $row->purchase_type,
                    'count' => $count,
                    'revenue' => round($revenue, 2),
                    'count_percent' => $totalCount > 0 ? round(($count / $totalCount) * 100, 1) : 0.0,
                    'revenue_percent' => $totalRevenue > 0 ? round(($revenue / $totalRevenue) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->all();

        $categories = [
            'avulso' => ['label' => 'Avulso', 'count' => 0, 'revenue' => 0.0],
            'pacote' => ['label' => 'Pacotes', 'count' => 0, 'revenue' => 0.0],
            'combo' => ['label' => 'Combos', 'count' => 0, 'revenue' => 0.0],
        ];

        foreach ($items as $item) {
            $category = match (true) {
                str_starts_with($item['purchase_type'], 'combo_') => 'combo',
                in_array($item['purchase_type'], [
                    ClientTreatmentPurchase::TYPE_PACKAGE,
                    ClientTreatmentPurchase::TYPE_PACKAGE_6,
                ], true) => 'pacote',
                default => 'avulso',
            };

            $categories[$category]['count'] += $item['count'];
            $categories[$category]['revenue'] += $item['revenue'];
        }

        $categoryItems = collect($categories)
            ->map(function (array $data, string $key) use ($totalRevenue, $totalCount) {
                return [
                    'category' => $key,
                    'label' => $data['label'],
                    'count' => $data['count'],
                    'revenue' => round($data['revenue'], 2),
                    'count_percent' => $totalCount > 0 ? round(($data['count'] / $totalCount) * 100, 1) : 0.0,
                    'revenue_percent' => $totalRevenue > 0 ? round(($data['revenue'] / $totalRevenue) * 100, 1) : 0.0,
                ];
            })
            ->filter(fn (array $category) => $category['count'] > 0)
            ->values()
            ->all();

        return [
            'total_revenue' => round($totalRevenue, 2),
            'total_count' => $totalCount,
            'items' => $items,
            'categories' => $categoryItems,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentSummary(Carbon $start, Carbon $end): array
    {
        $payments = ClientTreatmentPurchasePayment::query()
            ->join('client_treatment_purchases as purchases', 'purchases.id', '=', 'client_treatment_purchase_payments.client_treatment_purchase_id')
            ->where('purchases.is_courtesy', false)
            ->whereDate('purchases.purchased_at', '>=', $start->toDateString())
            ->whereDate('purchases.purchased_at', '<=', $end->toDateString())
            ->get([
                'client_treatment_purchase_payments.method',
                'client_treatment_purchase_payments.amount',
                'client_treatment_purchase_payments.installments',
                'client_treatment_purchase_payments.card_type',
            ]);

        $channels = [];
        $grossFromPayments = 0.0;
        $totalFees = 0.0;

        foreach ($payments as $payment) {
            $amount = (float) $payment->amount;
            $fee = $this->cardTerminalFeeService->feeAmount(
                $amount,
                $payment->method,
                $payment->card_type,
                $payment->installments,
            );
            $key = $this->cardTerminalFeeService->channelKey(
                $payment->method,
                $payment->card_type,
                $payment->installments,
            );

            $channels[$key] ??= [
                'channel' => $key,
                'label' => $this->cardTerminalFeeService->channelLabel(
                    $payment->method,
                    $payment->card_type,
                    $payment->installments,
                ),
                'fee_percent' => $this->cardTerminalFeeService->totalFeePercent(
                    $payment->method,
                    $payment->card_type,
                    $payment->installments,
                ),
                'gross' => 0.0,
                'fees' => 0.0,
                'net' => 0.0,
                'transaction_count' => 0,
            ];

            $channels[$key]['gross'] += $amount;
            $channels[$key]['fees'] += $fee;
            $channels[$key]['net'] += $amount - $fee;
            $channels[$key]['transaction_count']++;
            $grossFromPayments += $amount;
            $totalFees += $fee;
        }

        $grossRevenue = $this->totalRevenue($start, $end);
        $untrackedRevenue = round(max(0, $grossRevenue - $grossFromPayments), 2);
        $netRevenue = round($grossFromPayments - $totalFees + $untrackedRevenue, 2);

        $channelItems = collect($channels)
            ->map(function (array $channel) {
                return [
                    'channel' => $channel['channel'],
                    'label' => $channel['label'],
                    'fee_percent' => $channel['fee_percent'],
                    'gross' => round($channel['gross'], 2),
                    'fees' => round($channel['fees'], 2),
                    'net' => round($channel['net'], 2),
                    'transaction_count' => $channel['transaction_count'],
                ];
            })
            ->sortByDesc('gross')
            ->values()
            ->all();

        return [
            'gross_revenue' => round($grossRevenue, 2),
            'gross_from_payments' => round($grossFromPayments, 2),
            'untracked_revenue' => $untrackedRevenue,
            'total_fees' => round($totalFees, 2),
            'net_revenue' => $netRevenue,
            'fee_percent_of_gross' => $grossRevenue > 0
                ? round(($totalFees / $grossRevenue) * 100, 2)
                : 0.0,
            'anticipation_rate' => CardTerminalFeeService::ANTICIPATION_RATE,
            'fee_mode' => 'parcelado_vendedor',
            'channels' => $channelItems,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function appointmentStatus(Carbon $start, Carbon $end): array
    {
        $rows = Appointment::query()
            ->whereDate('scheduled_at', '>=', $start->toDateString())
            ->whereDate('scheduled_at', '<=', $end->toDateString())
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statusLabels = Appointment::statusLabels();
        $total = (int) $rows->sum('count');
        $completed = (int) ($rows->get(Appointment::STATUS_COMPLETED)?->count ?? 0);
        $cancelled = (int) ($rows->get(Appointment::STATUS_CANCELLED)?->count ?? 0);

        $chartLabels = [];
        $chartValues = [];

        foreach ([
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_CANCELLED,
            Appointment::STATUS_CONFIRMED,
            Appointment::STATUS_SCHEDULED,
        ] as $status) {
            $count = (int) ($rows->get($status)?->count ?? 0);

            if ($count > 0) {
                $chartLabels[] = $statusLabels[$status];
                $chartValues[] = $count;
            }
        }

        return [
            'summary' => [
                'total' => $total,
                'completed' => $completed,
                'cancelled' => $cancelled,
                'scheduled' => (int) ($rows->get(Appointment::STATUS_SCHEDULED)?->count ?? 0),
                'confirmed' => (int) ($rows->get(Appointment::STATUS_CONFIRMED)?->count ?? 0),
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0.0,
                'cancellation_rate' => $total > 0 ? round(($cancelled / $total) * 100, 1) : 0.0,
            ],
            'chart' => [
                'labels' => $chartLabels,
                'values' => $chartValues,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function professionalProductivity(Carbon $start, Carbon $end, int $limit = 10): array
    {
        $hoursByUser = Appointment::query()
            ->join('appointment_treatments', 'appointment_treatments.appointment_id', '=', 'appointments.id')
            ->join('treatments', 'treatments.id', '=', 'appointment_treatments.treatment_id')
            ->where('appointments.status', Appointment::STATUS_COMPLETED)
            ->whereDate('appointments.scheduled_at', '>=', $start->toDateString())
            ->whereDate('appointments.scheduled_at', '<=', $end->toDateString())
            ->groupBy('appointments.user_id')
            ->selectRaw('appointments.user_id as user_id, SUM(treatments.duration_minutes) as total_minutes')
            ->pluck('total_minutes', 'user_id');

        return Appointment::query()
            ->leftJoin('users', 'users.id', '=', 'appointments.user_id')
            ->whereDate('appointments.scheduled_at', '>=', $start->toDateString())
            ->whereDate('appointments.scheduled_at', '<=', $end->toDateString())
            ->groupBy('appointments.user_id', 'users.name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit($limit)
            ->get([
                'appointments.user_id',
                'users.name as professional_name',
                DB::raw('COUNT(*) as total_appointments'),
                DB::raw("SUM(CASE WHEN appointments.status = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN appointments.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled"),
                DB::raw('COUNT(DISTINCT appointments.client_id) as unique_clients'),
            ])
            ->map(function ($row) use ($hoursByUser) {
                $total = (int) $row->total_appointments;
                $cancelled = (int) $row->cancelled;
                $minutes = (int) ($hoursByUser[$row->user_id] ?? 0);

                return [
                    'user_id' => $row->user_id ? (int) $row->user_id : null,
                    'professional_name' => $row->professional_name ?? 'Sem profissional',
                    'total_appointments' => $total,
                    'completed' => (int) $row->completed,
                    'cancelled' => $cancelled,
                    'unique_clients' => (int) $row->unique_clients,
                    'hours' => round($minutes / 60, 1),
                    'cancellation_rate' => $total > 0 ? round(($cancelled / $total) * 100, 1) : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{summary: array<string, mixed>, items: list<array<string, mixed>>}
     */
    private function inactiveClientsWithSessions(int $limit = 20): array
    {
        $cutoff = now()->subDays(self::INACTIVE_CLIENT_DAYS)->startOfDay();

        $sessionRows = ClientTreatmentPurchaseItem::query()
            ->join('client_treatment_purchases as purchases', 'purchases.id', '=', 'client_treatment_purchase_items.client_treatment_purchase_id')
            ->join('clients', 'clients.id', '=', 'purchases.client_id')
            ->whereRaw('client_treatment_purchase_items.sessions_used < client_treatment_purchase_items.sessions_total')
            ->groupBy('clients.id', 'clients.name')
            ->get([
                'clients.id as client_id',
                'clients.name as client_name',
                DB::raw('SUM(client_treatment_purchase_items.sessions_total - client_treatment_purchase_items.sessions_used) as sessions_remaining'),
                DB::raw('SUM((client_treatment_purchase_items.sessions_total - client_treatment_purchase_items.sessions_used) * (CASE WHEN purchases.is_courtesy = 1 THEN 0 ELSE client_treatment_purchase_items.unit_price / NULLIF(client_treatment_purchase_items.sessions_total, 0) END)) as estimated_value'),
            ]);

        if ($sessionRows->isEmpty()) {
            return [
                'summary' => [
                    'inactive_days' => self::INACTIVE_CLIENT_DAYS,
                    'client_count' => 0,
                    'sessions_remaining' => 0,
                    'estimated_value' => 0.0,
                ],
                'items' => [],
            ];
        }

        $lastCompleted = Appointment::query()
            ->where('status', Appointment::STATUS_COMPLETED)
            ->whereIn('client_id', $sessionRows->pluck('client_id'))
            ->groupBy('client_id')
            ->selectRaw('client_id, MAX(scheduled_at) as last_completed_at')
            ->get()
            ->keyBy('client_id');

        $inactiveRows = $sessionRows->filter(function ($row) use ($lastCompleted, $cutoff) {
            $last = $lastCompleted->get($row->client_id);

            if (! $last) {
                return true;
            }

            return Carbon::parse($last->last_completed_at)->lt($cutoff);
        });

        $items = $inactiveRows
            ->map(function ($row) use ($lastCompleted) {
                $last = $lastCompleted->get($row->client_id);
                $lastAt = $last ? Carbon::parse($last->last_completed_at) : null;

                return [
                    'client_id' => (int) $row->client_id,
                    'client_name' => $row->client_name,
                    'sessions_remaining' => (int) $row->sessions_remaining,
                    'estimated_value' => round((float) $row->estimated_value, 2),
                    'last_completed_at' => $lastAt?->toDateString(),
                    'days_since_last_visit' => $lastAt?->diffInDays(now()),
                ];
            })
            ->sortByDesc('estimated_value')
            ->take($limit)
            ->values()
            ->all();

        return [
            'summary' => [
                'inactive_days' => self::INACTIVE_CLIENT_DAYS,
                'client_count' => $inactiveRows->count(),
                'sessions_remaining' => (int) $inactiveRows->sum('sessions_remaining'),
                'estimated_value' => round((float) $inactiveRows->sum('estimated_value'), 2),
            ],
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function peakHours(Carbon $start, Carbon $end): array
    {
        $dayLabels = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

        $appointments = Appointment::query()
            ->whereDate('scheduled_at', '>=', $start->toDateString())
            ->whereDate('scheduled_at', '<=', $end->toDateString())
            ->where('status', '!=', Appointment::STATUS_CANCELLED)
            ->get(['scheduled_at']);

        $counts = [];
        $minHour = 8;
        $maxHour = 18;

        foreach ($appointments as $appointment) {
            $at = Carbon::parse($appointment->scheduled_at);
            $dayIndex = $at->dayOfWeekIso - 1;
            $hour = $at->hour;

            $minHour = min($minHour, $hour);
            $maxHour = max($maxHour, $hour);
            $counts[$dayIndex][$hour] = ($counts[$dayIndex][$hour] ?? 0) + 1;
        }

        $hours = range($minHour, $maxHour);
        $matrix = [];
        $maxCount = 0;
        $busiest = null;

        for ($dayIndex = 0; $dayIndex < 7; $dayIndex++) {
            $row = [];

            foreach ($hours as $hour) {
                $count = $counts[$dayIndex][$hour] ?? 0;
                $row[] = $count;

                if ($count > $maxCount) {
                    $maxCount = $count;
                    $busiest = [
                        'day' => $dayLabels[$dayIndex],
                        'hour' => sprintf('%02d:00', $hour),
                        'count' => $count,
                    ];
                }
            }

            $matrix[] = $row;
        }

        return [
            'days' => $dayLabels,
            'hours' => array_map(fn (int $hour) => sprintf('%02d:00', $hour), $hours),
            'matrix' => $matrix,
            'max_count' => $maxCount,
            'busiest' => $busiest,
            'total' => $appointments->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function anamnesisReport(Carbon $start, Carbon $end): array
    {
        $invitations = AnamnesisInvitation::query()
            ->whereDate('created_at', '>=', $start->toDateString())
            ->whereDate('created_at', '<=', $end->toDateString())
            ->get();

        $sent = $invitations->count();
        $filled = $invitations->whereNotNull('used_at')->count();
        $expired = $invitations
            ->whereNull('used_at')
            ->filter(fn (AnamnesisInvitation $invitation) => $invitation->expires_at->isPast())
            ->count();
        $pending = $invitations
            ->whereNull('used_at')
            ->filter(fn (AnamnesisInvitation $invitation) => $invitation->expires_at->isFuture())
            ->count();

        $recordsCreated = AnamnesisRecord::query()
            ->whereDate('created_at', '>=', $start->toDateString())
            ->whereDate('created_at', '<=', $end->toDateString())
            ->count();

        $totalClients = Client::count();
        $clientsWithAnamnesis = Client::query()->whereHas('anamnesisRecords')->count();

        $recordIds = AnamnesisRecord::query()
            ->whereDate('created_at', '>=', $start->toDateString())
            ->whereDate('created_at', '<=', $end->toDateString())
            ->pluck('id');

        $questionStats = AnamnesisQuestion::query()
            ->where('is_active', true)
            ->whereIn('type', ['select', 'checkbox'])
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(function (AnamnesisQuestion $question) use ($recordIds) {
                if ($recordIds->isEmpty()) {
                    return null;
                }

                $answers = AnamnesisAnswer::query()
                    ->where('question_id', $question->id)
                    ->whereIn('anamnesis_record_id', $recordIds)
                    ->whereNotNull('answer')
                    ->get();

                if ($answers->isEmpty()) {
                    return null;
                }

                $aggregated = [];

                foreach ($answers as $answer) {
                    $label = $this->normalizeAnamnesisAnswerLabel($answer->answer);

                    if ($label === null || $label === '') {
                        continue;
                    }

                    $aggregated[$label] = ($aggregated[$label] ?? 0) + 1;
                }

                if ($aggregated === []) {
                    return null;
                }

                $totalResponses = array_sum($aggregated);
                arsort($aggregated);

                $responses = collect($aggregated)
                    ->map(fn (int $count, string $label) => [
                        'label' => $label,
                        'count' => $count,
                        'percent' => round(($count / $totalResponses) * 100, 1),
                    ])
                    ->values()
                    ->all();

                return [
                    'question_id' => $question->id,
                    'question' => $question->question,
                    'type' => $question->type,
                    'total_responses' => $totalResponses,
                    'responses' => $responses,
                ];
            })
            ->filter()
            ->take(5)
            ->values()
            ->all();

        return [
            'summary' => [
                'invitations_sent' => $sent,
                'invitations_filled' => $filled,
                'invitations_expired' => $expired,
                'invitations_pending' => $pending,
                'fill_rate' => $sent > 0 ? round(($filled / $sent) * 100, 1) : 0.0,
                'records_created' => $recordsCreated,
                'clients_without_anamnesis' => $totalClients - $clientsWithAnamnesis,
                'total_clients' => $totalClients,
            ],
            'question_stats' => $questionStats,
        ];
    }

    private function normalizeAnamnesisAnswerLabel(?string $answer): ?string
    {
        if ($answer === null || $answer === '') {
            return null;
        }

        $decoded = json_decode($answer, true);

        if (is_array($decoded) && array_key_exists('value', $decoded)) {
            $value = trim((string) $decoded['value']);
            $detail = trim((string) ($decoded['detail'] ?? ''));

            return $detail !== '' ? "{$value} — {$detail}" : $value;
        }

        return trim($answer);
    }

    /**
     * @return array<string, mixed>
     */
    private function notificationsReport(Carbon $start, Carbon $end, int $failureLimit = 10): array
    {
        $logs = NotificationLog::query()
            ->whereDate('created_at', '>=', $start->toDateString())
            ->whereDate('created_at', '<=', $end->toDateString())
            ->get();

        $total = $logs->count();
        $sent = $logs->where('status', 'sent')->count();
        $failed = $logs->where('status', 'failed')->count();

        $channelLabels = ['whatsapp' => 'WhatsApp', 'email' => 'E-mail'];
        $typeLabels = [
            'reminder' => 'Lembrete',
            'booking' => 'Confirmação de agendamento',
            'orientations' => 'Orientações',
            'anamnesis_request' => 'Solicitação de anamnese',
        ];

        $byChannel = $logs->groupBy('channel')
            ->map(function (Collection $items, string $channel) use ($channelLabels) {
                $channelTotal = $items->count();
                $channelSent = $items->where('status', 'sent')->count();
                $channelFailed = $items->where('status', 'failed')->count();

                return [
                    'channel' => $channel,
                    'label' => $channelLabels[$channel] ?? $channel,
                    'total' => $channelTotal,
                    'sent' => $channelSent,
                    'failed' => $channelFailed,
                    'delivery_rate' => $channelTotal > 0
                        ? round(($channelSent / $channelTotal) * 100, 1)
                        : 0.0,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        $byType = $logs->groupBy('type')
            ->map(function (Collection $items, string $type) use ($typeLabels) {
                return [
                    'type' => $type,
                    'label' => $typeLabels[$type] ?? $type,
                    'total' => $items->count(),
                    'sent' => $items->where('status', 'sent')->count(),
                    'failed' => $items->where('status', 'failed')->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        $recentFailures = NotificationLog::query()
            ->with('client:id,name')
            ->whereDate('created_at', '>=', $start->toDateString())
            ->whereDate('created_at', '<=', $end->toDateString())
            ->where('status', 'failed')
            ->orderByDesc('created_at')
            ->limit($failureLimit)
            ->get()
            ->map(fn (NotificationLog $log) => [
                'client_name' => $log->client?->name ?? '—',
                'channel' => $channelLabels[$log->channel] ?? $log->channel,
                'type' => $typeLabels[$log->type] ?? $log->type,
                'error_message' => $log->error_message,
                'created_at' => $log->created_at->toDateTimeString(),
            ])
            ->all();

        return [
            'summary' => [
                'total' => $total,
                'sent' => $sent,
                'failed' => $failed,
                'delivery_rate' => $total > 0 ? round(($sent / $total) * 100, 1) : 0.0,
            ],
            'by_channel' => $byChannel,
            'by_type' => $byType,
            'recent_failures' => $recentFailures,
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, items: list<array<string, mixed>>}
     */
    private function unusedSessions(int $limit = 20): array
    {
        /** @var Collection<int, object> $rows */
        $rows = ClientTreatmentPurchaseItem::query()
            ->join('client_treatment_purchases as purchases', 'purchases.id', '=', 'client_treatment_purchase_items.client_treatment_purchase_id')
            ->join('clients', 'clients.id', '=', 'purchases.client_id')
            ->join('treatments', 'treatments.id', '=', 'client_treatment_purchase_items.treatment_id')
            ->whereRaw('client_treatment_purchase_items.sessions_used < client_treatment_purchase_items.sessions_total')
            ->orderByDesc(DB::raw('(client_treatment_purchase_items.sessions_total - client_treatment_purchase_items.sessions_used)'))
            ->orderBy('clients.name')
            ->limit($limit)
            ->get([
                'clients.id as client_id',
                'clients.name as client_name',
                'treatments.name as treatment_name',
                'client_treatment_purchase_items.sessions_total',
                'client_treatment_purchase_items.sessions_used',
                'client_treatment_purchase_items.unit_price',
                'purchases.purchased_at',
                'purchases.is_courtesy',
            ]);

        $summaryRows = ClientTreatmentPurchaseItem::query()
            ->join('client_treatment_purchases as purchases', 'purchases.id', '=', 'client_treatment_purchase_items.client_treatment_purchase_id')
            ->whereRaw('client_treatment_purchase_items.sessions_used < client_treatment_purchase_items.sessions_total')
            ->selectRaw('SUM(client_treatment_purchase_items.sessions_total - client_treatment_purchase_items.sessions_used) as total_sessions')
            ->selectRaw('SUM((client_treatment_purchase_items.sessions_total - client_treatment_purchase_items.sessions_used) * (CASE WHEN purchases.is_courtesy = 1 THEN 0 ELSE client_treatment_purchase_items.unit_price / NULLIF(client_treatment_purchase_items.sessions_total, 0) END)) as estimated_value')
            ->selectRaw('COUNT(DISTINCT client_treatment_purchase_items.client_treatment_purchase_id) as purchase_count')
            ->first();

        $clientCount = (int) ClientTreatmentPurchaseItem::query()
            ->join('client_treatment_purchases as purchases', 'purchases.id', '=', 'client_treatment_purchase_items.client_treatment_purchase_id')
            ->whereRaw('client_treatment_purchase_items.sessions_used < client_treatment_purchase_items.sessions_total')
            ->distinct()
            ->count('purchases.client_id');

        $items = $rows->map(function ($row) {
            $remaining = (int) $row->sessions_total - (int) $row->sessions_used;
            $estimatedValue = $row->is_courtesy
                ? 0.0
                : ($row->sessions_total > 0
                    ? $remaining * ((float) $row->unit_price / (int) $row->sessions_total)
                    : 0.0);

            return [
                'client_id' => (int) $row->client_id,
                'client_name' => $row->client_name,
                'treatment_name' => $row->treatment_name,
                'sessions_remaining' => $remaining,
                'estimated_value' => round($estimatedValue, 2),
                'purchased_at' => Carbon::parse($row->purchased_at)->toDateString(),
            ];
        })->values()->all();

        return [
            'summary' => [
                'total_sessions' => (int) ($summaryRows->total_sessions ?? 0),
                'estimated_value' => round((float) ($summaryRows->estimated_value ?? 0), 2),
                'client_count' => $clientCount,
            ],
            'items' => $items,
        ];
    }
}
