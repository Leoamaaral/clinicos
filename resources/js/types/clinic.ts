export type ClientTreatmentPurchasePayment = {
    id: number;
    method: 'cash' | 'pix' | 'card';
    amount: string;
    installments: number | null;
    card_type: 'debit' | 'credit' | null;
};

export type ClientTreatmentPurchaseItem = {
    id: number;
    treatment_id: number;
    unit_price: string;
    sessions_total: number;
    sessions_used: number;
    combo_no_discount: boolean;
    treatment?: Treatment;
};

export type ClientTreatmentPurchase = {
    id: number;
    client_id: number;
    purchase_type:
        | 'single'
        | 'package_6'
        | 'package'
        | 'combo_single'
        | 'combo_package_6'
        | 'combo_package';
    total_price: string;
    calculated_price?: string | null;
    discount_percent?: string;
    is_courtesy?: boolean;
    purchased_at: string;
    notes: string | null;
    items?: ClientTreatmentPurchaseItem[];
    payments?: ClientTreatmentPurchasePayment[];
};

export type Client = {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    cpf: string;
    birth_date: string;
    notes: string | null;
    created_at: string;
    updated_at: string;
    appointments?: Appointment[];
    anamnesis_records?: AnamnesisRecord[];
    treatment_purchases?: ClientTreatmentPurchase[];
};

export type AnamnesisQuestion = {
    id: number;
    question: string;
    type: 'text' | 'select' | 'checkbox';
    options: string[] | null;
    order: number;
    is_active: boolean;
    is_required: boolean;
};

export type AnamnesisAnswer = {
    id: number;
    question_id: number;
    answer: string | null;
    formatted_answer: string | null;
    question?: AnamnesisQuestion;
};

export type AnamnesisRecord = {
    id: number;
    client_id: number;
    user_id: number | null;
    notes: string | null;
    created_at: string;
    answers?: AnamnesisAnswer[];
    user?: { id: number; name: string };
};

export type Treatment = {
    id: number;
    name: string;
    description: string | null;
    single_price: string;
    package_6_price: string;
    package_price: string;
    duration_minutes: number;
    image_path: string | null;
    image_url: string | null;
    is_active: boolean;
};

export type Appointment = {
    id: number;
    client_id: number;
    user_id: number | null;
    scheduled_at: string;
    scheduled_end_at: string | null;
    status: 'scheduled' | 'confirmed' | 'completed' | 'cancelled';
    notes: string | null;
    client?: Client;
    treatments?: Treatment[];
    professional?: { id: number; name: string };
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

export type ClinicSettings = {
    id: number;
    clinic_name: string;
    clinic_phone: string | null;
    clinic_email: string | null;
    whatsapp_days_before: number;
    whatsapp_enabled: boolean;
    whatsapp_message_template: string | null;
    whatsapp_booking_enabled: boolean;
    whatsapp_booking_message_template: string | null;
    whatsapp_orientations_enabled: boolean;
    whatsapp_orientations_message_template: string | null;
    email_days_before: number;
    email_enabled: boolean;
};

export type NotificationLog = {
    id: number;
    channel: string;
    type: string;
    status: string;
    message: string | null;
    sent_at: string | null;
    created_at: string;
    client?: { id: number; name: string };
    appointment?: { treatments?: { name: string }[] };
};

export type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'staff';
    created_at: string;
};
