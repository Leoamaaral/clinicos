export const routes = {
    dashboard: () => '/dashboard',
    clients: {
        index: () => '/clients',
        create: () => '/clients/create',
        show: (id: number) => `/clients/${id}`,
        availableTreatments: (clientId: number, params?: { exclude_appointment_id?: number; include_treatment_ids?: number[] }) => {
            const search = new URLSearchParams();

            if (params?.exclude_appointment_id) {
                search.set('exclude_appointment_id', String(params.exclude_appointment_id));
            }

            params?.include_treatment_ids?.forEach((id) => {
                search.append('include_treatment_ids[]', String(id));
            });
            const qs = search.toString();

            return qs
                ? `/clients/${clientId}/available-treatments?${qs}`
                : `/clients/${clientId}/available-treatments`;
        },
        edit: (id: number) => `/clients/${id}/edit`,
        anamnesisCreate: (id: number) => `/clients/${id}/anamnesis/create`,
        anamnesisRequest: (id: number) => `/clients/${id}/anamnesis/request`,
        anamnesisShow: (clientId: number, recordId: number) =>
            `/clients/${clientId}/anamnesis/${recordId}`,
    },
    appointments: {
        index: (date?: string) =>
            date ? `/appointments?date=${date}` : '/appointments',
        create: (params?: { client_id?: number; date?: string }) => {
            const search = new URLSearchParams();

            if (params?.client_id) {
search.set('client_id', String(params.client_id));
}

            if (params?.date) {
search.set('date', params.date);
}

            const qs = search.toString();

            return qs ? `/appointments/create?${qs}` : '/appointments/create';
        },
        edit: (id: number) => `/appointments/${id}/edit`,
        complete: {
            index: (params?: { date?: string; professional_id?: number | '' }) => {
                const search = new URLSearchParams();

                if (params?.date) {
                    search.set('date', params.date);
                }

                if (params?.professional_id) {
                    search.set('professional_id', String(params.professional_id));
                }

                const qs = search.toString();

                return qs ? `/appointments/complete?${qs}` : '/appointments/complete';
            },
            bulk: () => '/appointments/complete-bulk',
            store: (id: number) => `/appointments/${id}/complete`,
            destroy: (id: number) => `/appointments/${id}/uncomplete`,
        },
    },
    treatments: {
        index: () => '/treatments',
        create: () => '/treatments/create',
        edit: (id: number) => `/treatments/${id}/edit`,
    },
    quotes: {
        index: () => '/quotes',
    },
    reports: {
        index: (params?: { start_date?: string; end_date?: string }) => {
            const search = new URLSearchParams();
            if (params?.start_date) search.set('start_date', params.start_date);
            if (params?.end_date) search.set('end_date', params.end_date);
            const qs = search.toString();
            return qs ? `/reports?${qs}` : '/reports';
        },
        pdf: (params?: { start_date?: string; end_date?: string }) => {
            const search = new URLSearchParams();
            if (params?.start_date) search.set('start_date', params.start_date);
            if (params?.end_date) search.set('end_date', params.end_date);
            const qs = search.toString();
            return qs ? `/reports/pdf?${qs}` : '/reports/pdf';
        },
    },
    clientTreatments: {
        create: (clientId: number) => `/clients/${clientId}/treatments/create`,
        destroy: (clientId: number, purchaseId: number) =>
            `/clients/${clientId}/treatments/${purchaseId}`,
        preview: () => '/clients/treatments/preview',
    },
    anamnesisQuestions: {
        index: () => '/anamnesis-questions',
        create: () => '/anamnesis-questions/create',
        edit: (id: number) => `/anamnesis-questions/${id}/edit`,
    },
    admin: {
        users: {
            index: () => '/admin/users',
            create: () => '/admin/users/create',
            edit: (id: number) => `/admin/users/${id}/edit`,
        },
        settings: () => '/admin/settings',
    },
} as const;

export function formatCurrency(value: string | number): string {
    const num = typeof value === 'string' ? parseFloat(value) : value;

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(num);
}

export function formatDuration(minutes: number): string {
    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;

    if (remaining === 0) {
        return `${hours}h`;
    }

    return `${hours}h ${remaining}min`;
}

export function timeToMinutes(time: string): number {
    const [hours, minutes] = time.split(':').map(Number);

    return hours * 60 + minutes;
}

export function diffMinutesBetweenTimes(startTime: string, endTime: string): number {
    return timeToMinutes(endTime) - timeToMinutes(startTime);
}

export function timeRangeOverlapsBusyIntervals(
    startTime: string,
    endTime: string,
    busyIntervals: { start: string; end: string }[],
): boolean {
    const start = timeToMinutes(startTime);
    const end = timeToMinutes(endTime);

    return busyIntervals.some((interval) => {
        const busyStart = timeToMinutes(interval.start);
        const busyEnd = timeToMinutes(interval.end);

        return start < busyEnd && end > busyStart;
    });
}

export function parseLocalDateTime(value: string): Date {
    const normalized = value.includes('T') ? value : value.replace(' ', 'T');
    const [datePart, timePart = '00:00:00'] = normalized.slice(0, 19).split('T');
    const [y, m, d] = datePart.split('-').map(Number);
    const [hh, mm, ss = 0] = timePart.split(':').map(Number);

    return new Date(y, m - 1, d, hh, mm, ss);
}

export function formatLocalTime(value: string): string {
    return parseLocalDateTime(value).toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatDateKey(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

export function todayDateKey(): string {
    return formatDateKey(new Date());
}

export function isPastDateKey(date: string): boolean {
    return date < todayDateKey();
}

export function getWeekDays(weekStart: string): {
    date: string;
    dayName: string;
    dayNumber: number;
    isToday: boolean;
    isPast: boolean;
}[] {
    const days = [];
    const start = parseLocalDateTime(`${weekStart} 12:00:00`);
    const today = todayDateKey();

    for (let i = 0; i < 7; i++) {
        const day = new Date(start);
        day.setDate(start.getDate() + i);
        const date = formatDateKey(day);

        days.push({
            date,
            dayName: day.toLocaleDateString('pt-BR', { weekday: 'short' }),
            dayNumber: day.getDate(),
            isToday: date === today,
            isPast: date < today,
        });
    }

    return days;
}

export function formatWeekRange(weekStart: string, weekEnd: string): string {
    const start = parseLocalDateTime(`${weekStart} 12:00:00`);
    const end = parseLocalDateTime(`${weekEnd} 12:00:00`);

    const startLabel = start.toLocaleDateString('pt-BR', { day: 'numeric', month: 'short' });
    const endLabel = end.toLocaleDateString('pt-BR', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

    return `${startLabel} – ${endLabel}`;
}

export function appointmentDateKey(scheduledAt: string): string {
    return formatDateKey(parseLocalDateTime(scheduledAt));
}

export function formatLocalDateInput(value: string): string {
    return formatDateKey(parseLocalDateTime(value));
}

export function addMinutesToTime(date: string, time: string, minutes: number): string {
    return addMinutesToLocalDateTime(`${date} ${time}:00`, minutes);
}

export function addMinutesToLocalDateTime(value: string, minutes: number): string {
    const date = parseLocalDateTime(value);
    date.setMinutes(date.getMinutes() + minutes);

    return date.toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('pt-BR');
}

export function formatDateTime(date: string): string {
    return parseLocalDateTime(date).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatCpf(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 11);

    return digits
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}

export function formatPhone(value: string): string {
    const digits = value.replace(/\D/g, '').slice(0, 11);

    if (digits.length <= 10) {
        return digits.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3').trim();
    }

    return digits.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3').trim();
}

export function formatProfessionalLabel(
    professional?: { name: string } | null,
): string {
    return professional?.name ?? 'Qualquer profissional';
}

export function formatTreatmentLabel(treatment: { name: string }): string {
    return treatment.name;
}

export const appointmentStatusColors: Record<string, string> = {
    scheduled:
        'bg-rose-100 text-rose-900 dark:bg-rose-950/50 dark:text-rose-200',
    confirmed:
        'bg-teal-50 text-teal-900 dark:bg-teal-950/40 dark:text-teal-200',
    completed:
        'bg-stone-100 text-stone-700 dark:bg-stone-800/60 dark:text-stone-300',
    cancelled:
        'bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-300',
};

export type PurchasePaymentMethod = 'cash' | 'pix' | 'card';

export type PurchaseCardType = 'debit' | 'credit';

export type PurchasePaymentInput = {
    method: PurchasePaymentMethod;
    amount: string;
    installments: string;
    card_type: PurchaseCardType;
};

export type PurchaseDiscountRules = {
    staffMaxCardDiscount: number;
    staffMaxCashPixDiscount: number;
    mixedPaymentCashPixThreshold: number;
};

export const purchasePaymentMethodLabels: Record<PurchasePaymentMethod, string> = {
    cash: 'Dinheiro',
    pix: 'Pix',
    card: 'Cartão débito/crédito',
};

export function applyPurchaseDiscount(calculatedTotal: number, discountPercent: number): number {
    return Math.round(calculatedTotal * (1 - discountPercent / 100) * 100) / 100;
}

export function purchasePaymentsTotal(payments: PurchasePaymentInput[]): number {
    return Math.round(
        payments.reduce((sum, payment) => sum + (parseFloat(payment.amount) || 0), 0) * 100,
    ) / 100;
}

export function purchaseCashPixTotal(payments: PurchasePaymentInput[]): number {
    return Math.round(
        payments
            .filter((payment) => payment.method === 'cash' || payment.method === 'pix')
            .reduce((sum, payment) => sum + (parseFloat(payment.amount) || 0), 0) * 100,
    ) / 100;
}

export function maxPurchaseDiscountPercent(
    isAdmin: boolean,
    calculatedTotal: number,
    payments: PurchasePaymentInput[],
    discountPercent: number,
    rules: PurchaseDiscountRules,
): number {
    if (isAdmin) {
        return 100;
    }

    if (payments.length === 0) {
        return rules.staffMaxCashPixDiscount;
    }

    const finalTotal = applyPurchaseDiscount(calculatedTotal, discountPercent);
    const cashPixTotal = purchaseCashPixTotal(payments);
    const hasCard = payments.some((payment) => payment.method === 'card');
    const hasCashPix = cashPixTotal > 0;

    if (!hasCard && hasCashPix) {
        return rules.staffMaxCashPixDiscount;
    }

    if (hasCard && !hasCashPix) {
        return rules.staffMaxCardDiscount;
    }

    if (finalTotal > 0 && (cashPixTotal / finalTotal) * 100 >= rules.mixedPaymentCashPixThreshold) {
        return rules.staffMaxCashPixDiscount;
    }

    return rules.staffMaxCardDiscount;
}

export function createEmptyPurchasePayment(): PurchasePaymentInput {
    return {
        method: 'cash',
        amount: '',
        installments: '1',
        card_type: 'credit',
    };
}
