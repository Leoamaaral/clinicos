import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    Download,
    Gift,
    Minus,
    Receipt,
    ShoppingBag,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-react';
import { Fragment, type FormEvent, useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { PageHeader } from '@/components/page-header';
import { ResponsiveTable } from '@/components/responsive-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency, formatDate, routes } from '@/lib/clinic';

type ReportFilters = {
    start_date: string;
    end_date: string;
};

type ReportSummary = {
    revenue: number;
    purchase_count: number;
    paid_purchase_count: number;
    courtesy_count: number;
    courtesy_reference_value: number;
    courtesy_sessions: number;
    average_ticket: number;
    previous_revenue: number;
    revenue_change_percent: number | null;
};

type RevenueChart = {
    granularity: 'daily' | 'weekly' | 'monthly';
    labels: string[];
    revenue: number[];
    purchases: number[];
    courtesies: number[];
};

type TopTreatment = {
    treatment_id: number;
    treatment_name: string;
    revenue: number;
    sessions_sold: number;
    sales_count: number;
};

type TopClient = {
    client_id: number;
    client_name: string;
    total_spent: number;
    purchase_count: number;
    average_ticket: number;
    last_purchase: string;
};

type UnusedSessionItem = {
    client_id: number;
    client_name: string;
    treatment_name: string;
    sessions_remaining: number;
    estimated_value: number;
    purchased_at: string;
};

type SalesMixCategory = {
    category: string;
    label: string;
    count: number;
    revenue: number;
    count_percent: number;
    revenue_percent: number;
};

type SalesMixItem = SalesMixCategory & {
    purchase_type: string;
};

type SalesMix = {
    total_revenue: number;
    total_count: number;
    items: SalesMixItem[];
    categories: SalesMixCategory[];
};

type PaymentSummaryChannel = {
    channel: string;
    label: string;
    fee_percent: number;
    gross: number;
    fees: number;
    net: number;
    transaction_count: number;
};

type PaymentSummary = {
    gross_revenue: number;
    gross_from_payments: number;
    untracked_revenue: number;
    total_fees: number;
    net_revenue: number;
    fee_percent_of_gross: number;
    anticipation_rate: number;
    fee_mode: string;
    channels: PaymentSummaryChannel[];
};

type AppointmentStatus = {
    summary: {
        total: number;
        completed: number;
        cancelled: number;
        scheduled: number;
        confirmed: number;
        completion_rate: number;
        cancellation_rate: number;
    };
    chart: {
        labels: string[];
        values: number[];
    };
};

type ProfessionalProductivity = {
    user_id: number | null;
    professional_name: string;
    total_appointments: number;
    completed: number;
    cancelled: number;
    unique_clients: number;
    hours: number;
    cancellation_rate: number;
};

type InactiveClient = {
    client_id: number;
    client_name: string;
    sessions_remaining: number;
    estimated_value: number;
    last_completed_at: string | null;
    days_since_last_visit: number | null;
};

type PeakHours = {
    days: string[];
    hours: string[];
    matrix: number[][];
    max_count: number;
    busiest: { day: string; hour: string; count: number } | null;
    total: number;
};

type AnamnesisQuestionStat = {
    question_id: number;
    question: string;
    type: string;
    total_responses: number;
    responses: { label: string; count: number; percent: number }[];
};

type AnamnesisReport = {
    summary: {
        invitations_sent: number;
        invitations_filled: number;
        invitations_expired: number;
        invitations_pending: number;
        fill_rate: number;
        records_created: number;
        clients_without_anamnesis: number;
        total_clients: number;
    };
    question_stats: AnamnesisQuestionStat[];
};

type NotificationsReport = {
    summary: {
        total: number;
        sent: number;
        failed: number;
        delivery_rate: number;
    };
    by_channel: {
        channel: string;
        label: string;
        total: number;
        sent: number;
        failed: number;
        delivery_rate: number;
    }[];
    by_type: {
        type: string;
        label: string;
        total: number;
        sent: number;
        failed: number;
    }[];
    recent_failures: {
        client_name: string;
        channel: string;
        type: string;
        error_message: string | null;
        created_at: string;
    }[];
};

type Props = {
    clinic_name: string;
    filters: ReportFilters;
    summary: ReportSummary;
    revenue_chart: RevenueChart;
    top_treatments: TopTreatment[];
    top_clients: TopClient[];
    sales_mix: SalesMix;
    payment_summary: PaymentSummary;
    appointment_status: AppointmentStatus;
    professional_productivity: ProfessionalProductivity[];
    inactive_clients: {
        summary: {
            inactive_days: number;
            client_count: number;
            sessions_remaining: number;
            estimated_value: number;
        };
        items: InactiveClient[];
    };
    peak_hours: PeakHours;
    anamnesis: AnamnesisReport;
    notifications: NotificationsReport;
    unused_sessions: {
        summary: {
            total_sessions: number;
            estimated_value: number;
            client_count: number;
        };
        items: UnusedSessionItem[];
    };
};

const CHART_COLORS = {
    revenue: 'var(--chart-1)',
    purchases: 'var(--chart-2)',
    treatments: 'var(--chart-3)',
    clients: 'var(--chart-4)',
};

const PIE_COLORS = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
];

const granularityLabels: Record<RevenueChart['granularity'], string> = {
    daily: 'diário',
    weekly: 'semanal',
    monthly: 'mensal',
};

function peakCellOpacity(count: number, max: number): number {
    if (count === 0 || max === 0) {
        return 0;
    }

    return 0.12 + (count / max) * 0.88;
}

function PeakHoursHeatmap({ data }: { data: PeakHours }) {
    if (data.total === 0) {
        return (
            <p className="text-muted-foreground text-sm">Nenhum agendamento no período.</p>
        );
    }

    return (
        <div className="space-y-4">
            {data.busiest && (
                <p className="text-muted-foreground text-sm">
                    Horário de pico:{' '}
                    <span className="text-foreground font-medium">
                        {data.busiest.day} às {data.busiest.hour}
                    </span>{' '}
                    ({data.busiest.count} agendamentos)
                </p>
            )}
            <div className="overflow-x-auto">
                <div
                    className="inline-grid gap-1"
                    style={{
                        gridTemplateColumns: `4rem repeat(${data.hours.length}, minmax(2.25rem, 1fr))`,
                    }}
                >
                    <div />
                    {data.hours.map((hour) => (
                        <div
                            key={hour}
                            className="text-muted-foreground text-center text-[10px] font-medium"
                        >
                            {hour.slice(0, 2)}h
                        </div>
                    ))}
                    {data.days.map((day, dayIndex) => (
                        <Fragment key={day}>
                            <div className="text-muted-foreground flex items-center text-xs font-medium">
                                {day}
                            </div>
                            {data.matrix[dayIndex].map((count, hourIndex) => (
                                <div
                                    key={`${day}-${data.hours[hourIndex]}`}
                                    title={`${day} ${data.hours[hourIndex]}: ${count}`}
                                    className="flex aspect-square min-h-8 min-w-8 items-center justify-center rounded text-[10px] font-medium"
                                    style={{
                                        backgroundColor:
                                            count === 0
                                                ? 'var(--muted)'
                                                : `color-mix(in oklch, var(--chart-1) ${Math.round(peakCellOpacity(count, data.max_count) * 100)}%, var(--muted))`,
                                    }}
                                >
                                    {count > 0 ? count : ''}
                                </div>
                            ))}
                        </Fragment>
                    ))}
                </div>
            </div>
        </div>
    );
}

function ChangeIndicator({ value }: { value: number | null }) {
    if (value === null) {
        return (
            <span className="text-muted-foreground inline-flex items-center gap-1 text-xs">
                <Minus className="size-3" />
                sem base anterior
            </span>
        );
    }

    const isPositive = value > 0;
    const Icon = isPositive ? ArrowUpRight : value < 0 ? ArrowDownRight : Minus;

    return (
        <span
            className={
                isPositive
                    ? 'text-emerald-600 inline-flex items-center gap-1 text-xs'
                    : value < 0
                      ? 'text-destructive inline-flex items-center gap-1 text-xs'
                      : 'text-muted-foreground inline-flex items-center gap-1 text-xs'
            }
        >
            <Icon className="size-3" />
            {value > 0 ? '+' : ''}
            {value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}% vs período anterior
        </span>
    );
}

export default function ReportsIndex({
    clinic_name,
    filters,
    summary,
    revenue_chart,
    top_treatments,
    top_clients,
    sales_mix,
    payment_summary,
    appointment_status,
    professional_productivity,
    inactive_clients,
    peak_hours,
    anamnesis,
    notifications,
    unused_sessions,
}: Props) {
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);

    const revenueChartData = revenue_chart.labels.map((label, index) => ({
        label,
        revenue: revenue_chart.revenue[index],
        purchases: revenue_chart.purchases[index],
        courtesies: revenue_chart.courtesies[index] ?? 0,
    }));

    const treatmentChartData = top_treatments.map((treatment) => ({
        name: treatment.treatment_name.length > 24
            ? `${treatment.treatment_name.slice(0, 22)}…`
            : treatment.treatment_name,
        fullName: treatment.treatment_name,
        revenue: treatment.revenue,
    }));

    const clientChartData = top_clients.map((client) => ({
        name: client.client_name.length > 20
            ? `${client.client_name.slice(0, 18)}…`
            : client.client_name,
        fullName: client.client_name,
        total: client.total_spent,
    }));

    const salesMixChartData = sales_mix.categories.map((item) => ({
        name: item.label,
        revenue: item.revenue,
        count: item.count,
    }));

    const appointmentStatusChartData = appointment_status.chart.labels.map((label, index) => ({
        name: label,
        value: appointment_status.chart.values[index],
    }));

    const productivityChartData = professional_productivity.map((row) => ({
        name: row.professional_name.length > 18
            ? `${row.professional_name.slice(0, 16)}…`
            : row.professional_name,
        fullName: row.professional_name,
        completed: row.completed,
        hours: row.hours,
    }));

    function applyFilters(event: FormEvent) {
        event.preventDefault();
        router.get(
            routes.reports.index({ start_date: startDate, end_date: endDate }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    }

    const pdfUrl = routes.reports.pdf({ start_date: filters.start_date, end_date: filters.end_date });

    return (
        <>
            <Head title="Relatórios" />
            <div className="page-container">
                <PageHeader
                    title="Relatórios"
                    description={`Análise comercial — ${clinic_name}`}
                />

                <Card>
                    <CardContent className="pt-6">
                        <form
                            onSubmit={applyFilters}
                            className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end"
                        >
                            <div className="grid w-full gap-2 sm:w-auto">
                                <Label htmlFor="start_date">Data inicial</Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    required
                                />
                            </div>
                            <div className="grid w-full gap-2 sm:w-auto">
                                <Label htmlFor="end_date">Data final</Label>
                                <Input
                                    id="end_date"
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    required
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button type="submit">Filtrar</Button>
                                <Button variant="outline" asChild>
                                    <a href={pdfUrl}>
                                        <Download className="size-4" />
                                        Exportar PDF
                                    </a>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Faturamento</CardTitle>
                            <TrendingUp className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCurrency(summary.revenue)}</div>
                            <ChangeIndicator value={summary.revenue_change_percent} />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Compras pagas</CardTitle>
                            <ShoppingBag className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.paid_purchase_count}</div>
                            <p className="text-muted-foreground text-xs">no período selecionado</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Cortesias</CardTitle>
                            <Gift className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.courtesy_count}</div>
                            <p className="text-muted-foreground text-xs">
                                ref. {formatCurrency(summary.courtesy_reference_value)} •{' '}
                                {summary.courtesy_sessions} sessões cedidas
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Ticket médio</CardTitle>
                            <TrendingUp className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatCurrency(summary.average_ticket)}
                            </div>
                            <p className="text-muted-foreground text-xs">compras pagas</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Sessões pendentes</CardTitle>
                            <Users className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {unused_sessions.summary.total_sessions}
                            </div>
                            <p className="text-muted-foreground text-xs">
                                {formatCurrency(unused_sessions.summary.estimated_value)} estimados
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Receita líquida</CardTitle>
                            <Wallet className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatCurrency(payment_summary.net_revenue)}
                            </div>
                            <p className="text-muted-foreground text-xs">
                                após taxas da maquinha (antecipação {payment_summary.anticipation_rate}% a.m.)
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Taxas da maquinha</CardTitle>
                            <Receipt className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatCurrency(payment_summary.total_fees)}
                            </div>
                            <p className="text-muted-foreground text-xs">
                                {payment_summary.fee_percent_of_gross.toLocaleString('pt-BR', {
                                    maximumFractionDigits: 2,
                                })}
                                % do faturamento bruto
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Formas de pagamento</CardTitle>
                            <ShoppingBag className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {payment_summary.channels.length}
                            </div>
                            <p className="text-muted-foreground text-xs">
                                {payment_summary.untracked_revenue > 0
                                    ? `${formatCurrency(payment_summary.untracked_revenue)} sem detalhe de pagamento`
                                    : 'todas as vendas com pagamento detalhado'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Recebimento líquido por forma de pagamento</CardTitle>
                        <CardDescription>
                            Taxas Visa/Master com antecipação ({payment_summary.anticipation_rate}% a.m.) —
                            parcelado vendedor (taxas absorvidas pela clínica)
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {payment_summary.channels.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                Nenhum pagamento registrado no período.
                            </p>
                        ) : (
                            <ResponsiveTable
                                minWidth="640px"
                                mobile={payment_summary.channels.map((channel) => (
                                    <div key={channel.channel} className="rounded-lg border p-3">
                                        <p className="font-medium">{channel.label}</p>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            Taxa {channel.fee_percent.toLocaleString('pt-BR', {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2,
                                            })}
                                            % • {channel.transaction_count} transações
                                        </p>
                                        <p className="mt-1 text-sm">
                                            {formatCurrency(channel.gross)} bruto →{' '}
                                            {formatCurrency(channel.net)} líquido
                                        </p>
                                    </div>
                                ))}
                            >
                                <thead>
                                    <tr className="border-b">
                                        <th className="p-3 text-left font-medium">Forma</th>
                                        <th className="p-3 text-right font-medium">Taxa</th>
                                        <th className="p-3 text-right font-medium">Bruto</th>
                                        <th className="p-3 text-right font-medium">Taxas</th>
                                        <th className="p-3 text-right font-medium">Líquido</th>
                                        <th className="p-3 text-right font-medium">Qtd.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {payment_summary.channels.map((channel) => (
                                        <tr key={channel.channel} className="border-b last:border-0">
                                            <td className="p-3">{channel.label}</td>
                                            <td className="p-3 text-right">
                                                {channel.fee_percent.toLocaleString('pt-BR', {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2,
                                                })}
                                                %
                                            </td>
                                            <td className="p-3 text-right">
                                                {formatCurrency(channel.gross)}
                                            </td>
                                            <td className="p-3 text-right">
                                                {formatCurrency(channel.fees)}
                                            </td>
                                            <td className="p-3 text-right">
                                                {formatCurrency(channel.net)}
                                            </td>
                                            <td className="p-3 text-right">
                                                {channel.transaction_count}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </ResponsiveTable>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Faturamento por período</CardTitle>
                        <CardDescription>
                            Visão {granularityLabels[revenue_chart.granularity]} de receita, compras pagas e
                            cortesias
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {revenueChartData.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Nenhuma venda no período.</p>
                        ) : (
                            <div className="h-80 w-full">
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={revenueChartData} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                                        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                                        <XAxis
                                            dataKey="label"
                                            tick={{ fontSize: 11 }}
                                            interval="preserveStartEnd"
                                        />
                                        <YAxis
                                            yAxisId="revenue"
                                            tick={{ fontSize: 11 }}
                                            tickFormatter={(v) =>
                                                v >= 1000 ? `R$${(v / 1000).toFixed(0)}k` : `R$${v}`
                                            }
                                        />
                                        <YAxis
                                            yAxisId="purchases"
                                            orientation="right"
                                            allowDecimals={false}
                                            tick={{ fontSize: 11 }}
                                        />
                                        <Tooltip
                                            formatter={(value, name) => {
                                                if (name === 'revenue') {
                                                    return [formatCurrency(Number(value)), 'Faturamento'];
                                                }
                                                if (name === 'purchases') {
                                                    return [value, 'Compras pagas'];
                                                }

                                                return [value, 'Cortesias'];
                                            }}
                                            labelFormatter={(label) => `Período: ${label}`}
                                        />
                                        <Legend
                                            formatter={(value) => {
                                                if (value === 'revenue') {
                                                    return 'Faturamento';
                                                }
                                                if (value === 'purchases') {
                                                    return 'Compras pagas';
                                                }

                                                return 'Cortesias';
                                            }}
                                        />
                                        <Bar
                                            yAxisId="revenue"
                                            dataKey="revenue"
                                            name="revenue"
                                            fill={CHART_COLORS.revenue}
                                            radius={[4, 4, 0, 0]}
                                        />
                                        <Bar
                                            yAxisId="purchases"
                                            dataKey="purchases"
                                            name="purchases"
                                            fill={CHART_COLORS.purchases}
                                            radius={[4, 4, 0, 0]}
                                        />
                                        <Bar
                                            yAxisId="purchases"
                                            dataKey="courtesies"
                                            name="courtesies"
                                            fill="var(--chart-3)"
                                            radius={[4, 4, 0, 0]}
                                        />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-4 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Tratamentos mais vendidos</CardTitle>
                            <CardDescription>
                                Top 10 por receita no período (apenas compras pagas)
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {treatmentChartData.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    Nenhum tratamento vendido no período.
                                </p>
                            ) : (
                                <div className="h-72 w-full">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <BarChart
                                            data={treatmentChartData}
                                            layout="vertical"
                                            margin={{ top: 0, right: 16, left: 8, bottom: 0 }}
                                        >
                                            <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                                            <XAxis
                                                type="number"
                                                tick={{ fontSize: 11 }}
                                                tickFormatter={(v) => formatCurrency(v)}
                                            />
                                            <YAxis
                                                type="category"
                                                dataKey="name"
                                                width={110}
                                                tick={{ fontSize: 11 }}
                                            />
                                            <Tooltip
                                                formatter={(value) => [formatCurrency(Number(value)), 'Receita']}
                                                labelFormatter={(_, payload) =>
                                                    payload?.[0]?.payload?.fullName ?? ''
                                                }
                                            />
                                            <Bar
                                                dataKey="revenue"
                                                fill={CHART_COLORS.treatments}
                                                radius={[0, 4, 4, 0]}
                                            />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Clientes que mais compram</CardTitle>
                            <CardDescription>Top 10 por valor gasto no período</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {clientChartData.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    Nenhum cliente com compras no período.
                                </p>
                            ) : (
                                <div className="h-72 w-full">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <BarChart
                                            data={clientChartData}
                                            layout="vertical"
                                            margin={{ top: 0, right: 16, left: 8, bottom: 0 }}
                                        >
                                            <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                                            <XAxis
                                                type="number"
                                                tick={{ fontSize: 11 }}
                                                tickFormatter={(v) => formatCurrency(v)}
                                            />
                                            <YAxis
                                                type="category"
                                                dataKey="name"
                                                width={100}
                                                tick={{ fontSize: 11 }}
                                            />
                                            <Tooltip
                                                formatter={(value) => [formatCurrency(Number(value)), 'Total gasto']}
                                                labelFormatter={(_, payload) =>
                                                    payload?.[0]?.payload?.fullName ?? ''
                                                }
                                            />
                                            <Bar
                                                dataKey="total"
                                                fill={CHART_COLORS.clients}
                                                radius={[0, 4, 4, 0]}
                                            />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Detalhe — tratamentos</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveTable
                                minWidth="520px"
                                mobile={
                                    top_treatments.length === 0 ? (
                                        <p className="text-muted-foreground text-sm">Nenhum dado.</p>
                                    ) : (
                                        top_treatments.map((treatment, index) => (
                                            <div key={treatment.treatment_id} className="rounded-lg border p-3">
                                                <p className="font-medium">
                                                    {index + 1}. {treatment.treatment_name}
                                                </p>
                                                <p className="text-muted-foreground mt-1 text-sm">
                                                    {formatCurrency(treatment.revenue)} •{' '}
                                                    {treatment.sessions_sold} sessões •{' '}
                                                    {treatment.sales_count} vendas
                                                </p>
                                            </div>
                                        ))
                                    )
                                }
                            >
                                <thead>
                                    <tr className="border-b">
                                        <th className="p-3 text-left font-medium">#</th>
                                        <th className="p-3 text-left font-medium">Tratamento</th>
                                        <th className="p-3 text-right font-medium">Receita</th>
                                        <th className="p-3 text-right font-medium">Sessões</th>
                                        <th className="p-3 text-right font-medium">Vendas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {top_treatments.map((treatment, index) => (
                                        <tr key={treatment.treatment_id} className="border-b last:border-0">
                                            <td className="p-3">{index + 1}</td>
                                            <td className="p-3">{treatment.treatment_name}</td>
                                            <td className="p-3 text-right">
                                                {formatCurrency(treatment.revenue)}
                                            </td>
                                            <td className="p-3 text-right">{treatment.sessions_sold}</td>
                                            <td className="p-3 text-right">{treatment.sales_count}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </ResponsiveTable>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Detalhe — clientes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveTable
                                minWidth="560px"
                                mobile={
                                    top_clients.length === 0 ? (
                                        <p className="text-muted-foreground text-sm">Nenhum dado.</p>
                                    ) : (
                                        top_clients.map((client, index) => (
                                            <div key={client.client_id} className="rounded-lg border p-3">
                                                <p className="font-medium">
                                                    {index + 1}.{' '}
                                                    <Link
                                                        href={routes.clients.show(client.client_id)}
                                                        className="hover:underline"
                                                    >
                                                        {client.client_name}
                                                    </Link>
                                                </p>
                                                <p className="text-muted-foreground mt-1 text-sm">
                                                    {formatCurrency(client.total_spent)} •{' '}
                                                    {client.purchase_count} compras • última em{' '}
                                                    {formatDate(client.last_purchase)}
                                                </p>
                                            </div>
                                        ))
                                    )
                                }
                            >
                                <thead>
                                    <tr className="border-b">
                                        <th className="p-3 text-left font-medium">#</th>
                                        <th className="p-3 text-left font-medium">Cliente</th>
                                        <th className="p-3 text-right font-medium">Total</th>
                                        <th className="p-3 text-right font-medium">Compras</th>
                                        <th className="p-3 text-left font-medium">Última</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {top_clients.map((client, index) => (
                                        <tr key={client.client_id} className="border-b last:border-0">
                                            <td className="p-3">{index + 1}</td>
                                            <td className="p-3">
                                                <Link
                                                    href={routes.clients.show(client.client_id)}
                                                    className="hover:underline"
                                                >
                                                    {client.client_name}
                                                </Link>
                                            </td>
                                            <td className="p-3 text-right">
                                                {formatCurrency(client.total_spent)}
                                            </td>
                                            <td className="p-3 text-right">{client.purchase_count}</td>
                                            <td className="p-3">{formatDate(client.last_purchase)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </ResponsiveTable>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Mix de vendas</CardTitle>
                            <CardDescription>
                                Distribuição por tipo de compra no período (
                                {sales_mix.total_count} vendas)
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {salesMixChartData.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    Nenhuma venda no período.
                                </p>
                            ) : (
                                <div className="grid gap-6 lg:grid-cols-2">
                                    <div className="h-64 w-full">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <PieChart>
                                                <Pie
                                                    data={salesMixChartData}
                                                    dataKey="revenue"
                                                    nameKey="name"
                                                    cx="50%"
                                                    cy="50%"
                                                    innerRadius={50}
                                                    outerRadius={80}
                                                    paddingAngle={2}
                                                >
                                                    {salesMixChartData.map((_, index) => (
                                                        <Cell
                                                            key={index}
                                                            fill={PIE_COLORS[index % PIE_COLORS.length]}
                                                        />
                                                    ))}
                                                </Pie>
                                                <Tooltip
                                                    formatter={(value) => [
                                                        formatCurrency(Number(value)),
                                                        'Receita',
                                                    ]}
                                                />
                                                <Legend />
                                            </PieChart>
                                        </ResponsiveContainer>
                                    </div>
                                    <div className="space-y-2">
                                        {sales_mix.items.map((item) => (
                                            <div
                                                key={item.purchase_type}
                                                className="flex items-center justify-between rounded-lg border p-3 text-sm"
                                            >
                                                <div>
                                                    <p className="font-medium">{item.label}</p>
                                                    <p className="text-muted-foreground">
                                                        {item.count} vendas ({item.count_percent}%)
                                                    </p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="font-medium">
                                                        {formatCurrency(item.revenue)}
                                                    </p>
                                                    <p className="text-muted-foreground">
                                                        {item.revenue_percent}%
                                                    </p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Status da agenda</CardTitle>
                            <CardDescription>
                                {appointment_status.summary.total} agendamentos no período
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {appointment_status.summary.total === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    Nenhum agendamento no período.
                                </p>
                            ) : (
                                <div className="grid gap-6 lg:grid-cols-2">
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="rounded-lg border p-3">
                                            <p className="text-muted-foreground text-xs">Conclusão</p>
                                            <p className="text-xl font-bold">
                                                {appointment_status.summary.completion_rate}%
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {appointment_status.summary.completed} concluídos
                                            </p>
                                        </div>
                                        <div className="rounded-lg border p-3">
                                            <p className="text-muted-foreground text-xs">Cancelamento</p>
                                            <p className="text-xl font-bold">
                                                {appointment_status.summary.cancellation_rate}%
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {appointment_status.summary.cancelled} cancelados
                                            </p>
                                        </div>
                                        <div className="rounded-lg border p-3">
                                            <p className="text-muted-foreground text-xs">Confirmados</p>
                                            <p className="text-xl font-bold">
                                                {appointment_status.summary.confirmed}
                                            </p>
                                        </div>
                                        <div className="rounded-lg border p-3">
                                            <p className="text-muted-foreground text-xs">Agendados</p>
                                            <p className="text-xl font-bold">
                                                {appointment_status.summary.scheduled}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="h-64 w-full">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <PieChart>
                                                <Pie
                                                    data={appointmentStatusChartData}
                                                    dataKey="value"
                                                    nameKey="name"
                                                    cx="50%"
                                                    cy="50%"
                                                    outerRadius={80}
                                                    paddingAngle={2}
                                                >
                                                    {appointmentStatusChartData.map((_, index) => (
                                                        <Cell
                                                            key={index}
                                                            fill={PIE_COLORS[index % PIE_COLORS.length]}
                                                        />
                                                    ))}
                                                </Pie>
                                                <Tooltip />
                                                <Legend />
                                            </PieChart>
                                        </ResponsiveContainer>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Produtividade por profissional</CardTitle>
                            <CardDescription>
                                Agendamentos e horas concluídas no período
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {productivityChartData.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    Nenhum agendamento no período.
                                </p>
                            ) : (
                                <>
                                    <div className="mb-6 h-64 w-full">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <BarChart
                                                data={productivityChartData}
                                                margin={{ top: 8, right: 8, left: 0, bottom: 0 }}
                                            >
                                                <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                                                <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                                                <YAxis yAxisId="completed" allowDecimals={false} tick={{ fontSize: 11 }} />
                                                <YAxis
                                                    yAxisId="hours"
                                                    orientation="right"
                                                    tick={{ fontSize: 11 }}
                                                />
                                                <Tooltip
                                                    labelFormatter={(_, payload) =>
                                                        payload?.[0]?.payload?.fullName ?? ''
                                                    }
                                                />
                                                <Legend
                                                    formatter={(value) =>
                                                        value === 'completed' ? 'Concluídos' : 'Horas'
                                                    }
                                                />
                                                <Bar
                                                    yAxisId="completed"
                                                    dataKey="completed"
                                                    name="completed"
                                                    fill={CHART_COLORS.treatments}
                                                    radius={[4, 4, 0, 0]}
                                                />
                                                <Bar
                                                    yAxisId="hours"
                                                    dataKey="hours"
                                                    name="hours"
                                                    fill={CHART_COLORS.clients}
                                                    radius={[4, 4, 0, 0]}
                                                />
                                            </BarChart>
                                        </ResponsiveContainer>
                                    </div>
                                    <ResponsiveTable
                                        minWidth="560px"
                                        mobile={
                                            professional_productivity.map((row) => (
                                                <div key={row.user_id ?? 'none'} className="rounded-lg border p-3">
                                                    <p className="font-medium">{row.professional_name}</p>
                                                    <p className="text-muted-foreground mt-1 text-sm">
                                                        {row.completed}/{row.total_appointments} concluídos •{' '}
                                                        {row.hours}h • {row.unique_clients} clientes •{' '}
                                                        {row.cancellation_rate}% cancel.
                                                    </p>
                                                </div>
                                            ))
                                        }
                                    >
                                        <thead>
                                            <tr className="border-b">
                                                <th className="p-3 text-left font-medium">Profissional</th>
                                                <th className="p-3 text-right font-medium">Total</th>
                                                <th className="p-3 text-right font-medium">Concl.</th>
                                                <th className="p-3 text-right font-medium">Horas</th>
                                                <th className="p-3 text-right font-medium">Clientes</th>
                                                <th className="p-3 text-right font-medium">Cancel.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {professional_productivity.map((row) => (
                                                <tr
                                                    key={row.user_id ?? 'none'}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="p-3">{row.professional_name}</td>
                                                    <td className="p-3 text-right">{row.total_appointments}</td>
                                                    <td className="p-3 text-right">{row.completed}</td>
                                                    <td className="p-3 text-right">{row.hours}h</td>
                                                    <td className="p-3 text-right">{row.unique_clients}</td>
                                                    <td className="p-3 text-right">{row.cancellation_rate}%</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </ResponsiveTable>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Clientes inativos com sessões</CardTitle>
                            <CardDescription>
                                Sem atendimento concluído há {inactive_clients.summary.inactive_days} dias
                                — {inactive_clients.summary.client_count} clientes (
                                {inactive_clients.summary.sessions_remaining} sessões,{' '}
                                {formatCurrency(inactive_clients.summary.estimated_value)})
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveTable
                                minWidth="600px"
                                mobile={
                                    inactive_clients.items.length === 0 ? (
                                        <p className="text-muted-foreground text-sm">
                                            Nenhum cliente inativo com sessões.
                                        </p>
                                    ) : (
                                        inactive_clients.items.map((client) => (
                                            <div key={client.client_id} className="rounded-lg border p-3">
                                                <p className="font-medium">
                                                    <Link
                                                        href={routes.clients.show(client.client_id)}
                                                        className="hover:underline"
                                                    >
                                                        {client.client_name}
                                                    </Link>
                                                </p>
                                                <p className="text-muted-foreground mt-1 text-sm">
                                                    {client.sessions_remaining} sessões •{' '}
                                                    {formatCurrency(client.estimated_value)} •{' '}
                                                    {client.last_completed_at
                                                        ? `última visita há ${client.days_since_last_visit} dias`
                                                        : 'nunca concluiu atendimento'}
                                                </p>
                                            </div>
                                        ))
                                    )
                                }
                            >
                                <thead>
                                    <tr className="border-b">
                                        <th className="p-3 text-left font-medium">Cliente</th>
                                        <th className="p-3 text-right font-medium">Sessões</th>
                                        <th className="p-3 text-right font-medium">Valor est.</th>
                                        <th className="p-3 text-left font-medium">Última visita</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {inactive_clients.items.map((client) => (
                                        <tr key={client.client_id} className="border-b last:border-0">
                                            <td className="p-3">
                                                <Link
                                                    href={routes.clients.show(client.client_id)}
                                                    className="hover:underline"
                                                >
                                                    {client.client_name}
                                                </Link>
                                            </td>
                                            <td className="p-3 text-right">{client.sessions_remaining}</td>
                                            <td className="p-3 text-right">
                                                {formatCurrency(client.estimated_value)}
                                            </td>
                                            <td className="p-3">
                                                {client.last_completed_at
                                                    ? `${formatDate(client.last_completed_at)} (${client.days_since_last_visit}d)`
                                                    : 'Nunca'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </ResponsiveTable>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Horários de pico</CardTitle>
                            <CardDescription>
                                Distribuição de agendamentos por dia e hora ({peak_hours.total}{' '}
                                no período)
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <PeakHoursHeatmap data={peak_hours} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Anamnese</CardTitle>
                            <CardDescription>
                                Convites e fichas no período —{' '}
                                {anamnesis.summary.clients_without_anamnesis} de{' '}
                                {anamnesis.summary.total_clients} clientes sem ficha
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div className="rounded-lg border p-3">
                                    <p className="text-muted-foreground text-xs">Convites</p>
                                    <p className="text-xl font-bold">
                                        {anamnesis.summary.invitations_sent}
                                    </p>
                                </div>
                                <div className="rounded-lg border p-3">
                                    <p className="text-muted-foreground text-xs">Preenchidos</p>
                                    <p className="text-xl font-bold">
                                        {anamnesis.summary.invitations_filled}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {anamnesis.summary.fill_rate}% taxa
                                    </p>
                                </div>
                                <div className="rounded-lg border p-3">
                                    <p className="text-muted-foreground text-xs">Fichas criadas</p>
                                    <p className="text-xl font-bold">
                                        {anamnesis.summary.records_created}
                                    </p>
                                </div>
                                <div className="rounded-lg border p-3">
                                    <p className="text-muted-foreground text-xs">Expirados</p>
                                    <p className="text-xl font-bold">
                                        {anamnesis.summary.invitations_expired}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {anamnesis.summary.invitations_pending} pendentes
                                    </p>
                                </div>
                            </div>

                            {anamnesis.question_stats.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    Nenhuma resposta agregável no período.
                                </p>
                            ) : (
                                <div className="space-y-4">
                                    {anamnesis.question_stats.map((question) => (
                                        <div key={question.question_id} className="rounded-lg border p-4">
                                            <p className="font-medium">{question.question}</p>
                                            <p className="text-muted-foreground mb-3 text-xs">
                                                {question.total_responses} respostas
                                            </p>
                                            <div className="space-y-2">
                                                {question.responses.map((response) => (
                                                    <div key={response.label}>
                                                        <div className="mb-1 flex justify-between text-sm">
                                                            <span>{response.label}</span>
                                                            <span className="text-muted-foreground">
                                                                {response.count} ({response.percent}%)
                                                            </span>
                                                        </div>
                                                        <div className="bg-muted h-2 overflow-hidden rounded-full">
                                                            <div
                                                                className="bg-primary h-full rounded-full"
                                                                style={{ width: `${response.percent}%` }}
                                                            />
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Comunicações</CardTitle>
                        <CardDescription>
                            Notificações enviadas no período —{' '}
                            {notifications.summary.delivery_rate}% de entrega (
                            {notifications.summary.sent}/{notifications.summary.total})
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {notifications.summary.total === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                Nenhuma notificação no período.
                            </p>
                        ) : (
                            <>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <p className="mb-2 text-sm font-medium">Por canal</p>
                                        <div className="space-y-2">
                                            {notifications.by_channel.map((row) => (
                                                <div
                                                    key={row.channel}
                                                    className="flex items-center justify-between rounded-lg border p-3 text-sm"
                                                >
                                                    <div>
                                                        <p className="font-medium">{row.label}</p>
                                                        <p className="text-muted-foreground">
                                                            {row.sent} enviadas • {row.failed} falhas
                                                        </p>
                                                    </div>
                                                    <p className="font-medium">{row.delivery_rate}%</p>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                    <div>
                                        <p className="mb-2 text-sm font-medium">Por tipo</p>
                                        <div className="h-56 w-full">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <BarChart
                                                    data={notifications.by_type}
                                                    layout="vertical"
                                                    margin={{ top: 0, right: 16, left: 8, bottom: 0 }}
                                                >
                                                    <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                                                    <XAxis type="number" allowDecimals={false} tick={{ fontSize: 11 }} />
                                                    <YAxis
                                                        type="category"
                                                        dataKey="label"
                                                        width={120}
                                                        tick={{ fontSize: 10 }}
                                                    />
                                                    <Tooltip />
                                                    <Bar
                                                        dataKey="total"
                                                        name="Total"
                                                        fill={CHART_COLORS.revenue}
                                                        radius={[0, 4, 4, 0]}
                                                    />
                                                </BarChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </div>
                                </div>

                                {notifications.recent_failures.length > 0 && (
                                    <div>
                                        <p className="mb-2 text-sm font-medium">Falhas recentes</p>
                                        <ResponsiveTable
                                            minWidth="560px"
                                            mobile={notifications.recent_failures.map((failure, index) => (
                                                <div key={index} className="rounded-lg border p-3 text-sm">
                                                    <p className="font-medium">{failure.client_name}</p>
                                                    <p className="text-muted-foreground mt-1">
                                                        {failure.channel} • {failure.type}
                                                    </p>
                                                    <p className="text-destructive mt-1 text-xs">
                                                        {failure.error_message ?? 'Erro desconhecido'}
                                                    </p>
                                                </div>
                                            ))}
                                        >
                                            <thead>
                                                <tr className="border-b">
                                                    <th className="p-3 text-left font-medium">Cliente</th>
                                                    <th className="p-3 text-left font-medium">Canal</th>
                                                    <th className="p-3 text-left font-medium">Tipo</th>
                                                    <th className="p-3 text-left font-medium">Erro</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {notifications.recent_failures.map((failure, index) => (
                                                    <tr key={index} className="border-b last:border-0">
                                                        <td className="p-3">{failure.client_name}</td>
                                                        <td className="p-3">{failure.channel}</td>
                                                        <td className="p-3">{failure.type}</td>
                                                        <td className="text-destructive p-3 text-xs">
                                                            {failure.error_message ?? '—'}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </ResponsiveTable>
                                    </div>
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Sessões não utilizadas</CardTitle>
                        <CardDescription>
                            Situação atual — {unused_sessions.summary.client_count} clientes com{' '}
                            {unused_sessions.summary.total_sessions} sessões pendentes (
                            {formatCurrency(unused_sessions.summary.estimated_value)} estimados)
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ResponsiveTable
                            minWidth="640px"
                            mobile={
                                unused_sessions.items.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">
                                        Nenhuma sessão pendente.
                                    </p>
                                ) : (
                                    unused_sessions.items.map((item) => (
                                        <div
                                            key={`${item.client_id}-${item.treatment_name}`}
                                            className="rounded-lg border p-3"
                                        >
                                            <p className="font-medium">
                                                <Link
                                                    href={routes.clients.show(item.client_id)}
                                                    className="hover:underline"
                                                >
                                                    {item.client_name}
                                                </Link>
                                            </p>
                                            <p className="text-muted-foreground mt-1 text-sm">
                                                {item.treatment_name} • {item.sessions_remaining} sessões •{' '}
                                                {formatCurrency(item.estimated_value)} • compra em{' '}
                                                {formatDate(item.purchased_at)}
                                            </p>
                                        </div>
                                    ))
                                )
                            }
                        >
                            <thead>
                                <tr className="border-b">
                                    <th className="p-3 text-left font-medium">Cliente</th>
                                    <th className="p-3 text-left font-medium">Tratamento</th>
                                    <th className="p-3 text-right font-medium">Sessões</th>
                                    <th className="p-3 text-right font-medium">Valor est.</th>
                                    <th className="p-3 text-left font-medium">Compra em</th>
                                </tr>
                            </thead>
                            <tbody>
                                {unused_sessions.items.map((item) => (
                                    <tr
                                        key={`${item.client_id}-${item.treatment_name}`}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3">
                                            <Link
                                                href={routes.clients.show(item.client_id)}
                                                className="hover:underline"
                                            >
                                                {item.client_name}
                                            </Link>
                                        </td>
                                        <td className="p-3">{item.treatment_name}</td>
                                        <td className="p-3 text-right">{item.sessions_remaining}</td>
                                        <td className="p-3 text-right">
                                            {formatCurrency(item.estimated_value)}
                                        </td>
                                        <td className="p-3">{formatDate(item.purchased_at)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </ResponsiveTable>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: routes.dashboard() },
        { title: 'Relatórios', href: routes.reports.index() },
    ],
};
