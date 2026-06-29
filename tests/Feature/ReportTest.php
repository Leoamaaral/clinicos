<?php

namespace Tests\Feature;

use App\Models\AnamnesisInvitation;
use App\Models\AnamnesisQuestion;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientTreatmentPurchase;
use App\Models\ClientTreatmentPurchaseItem;
use App\Models\NotificationLog;
use App\Models\Treatment;
use App\Models\User;
use App\Services\AnamnesisRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private Treatment $treatment;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->treatment = Treatment::create([
            'name' => 'Depilação a laser - AXILA',
            'single_price' => 200,
            'package_6_price' => 1080,
            'package_price' => 1200,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $this->client = Client::create([
            'name' => 'Maria Silva',
            'phone' => '11999999999',
            'email' => 'maria@example.com',
            'cpf' => '12345678901',
            'birth_date' => '1990-01-01',
        ]);
    }

    public function test_authenticated_user_can_view_reports_page(): void
    {
        $user = User::factory()->create();

        $this->seedPurchase(totalPrice: 500, purchasedAt: now()->toDateString(), sessionsTotal: 10, sessionsUsed: 3);

        $this->actingAs($user)
            ->get('/reports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/index')
                ->has('summary')
                ->has('revenue_chart')
                ->has('top_treatments')
                ->has('top_clients')
                ->has('sales_mix')
                ->has('payment_summary')
                ->has('appointment_status')
                ->has('professional_productivity')
                ->has('inactive_clients')
                ->has('peak_hours')
                ->has('anamnesis')
                ->has('notifications')
                ->has('unused_sessions')
                ->where('summary.revenue', 500)
                ->where('summary.purchase_count', 1)
                ->where('unused_sessions.summary.total_sessions', 7));
    }

    public function test_reports_can_be_filtered_by_date_range(): void
    {
        $user = User::factory()->create();

        $this->seedPurchase(totalPrice: 100, purchasedAt: '2026-01-15', sessionsTotal: 1, sessionsUsed: 0);
        $this->seedPurchase(totalPrice: 300, purchasedAt: '2026-02-10', sessionsTotal: 1, sessionsUsed: 0);

        $this->actingAs($user)
            ->get('/reports?start_date=2026-02-01&end_date=2026-02-28')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.revenue', 300)
                ->where('summary.purchase_count', 1));
    }

    public function test_reports_include_sales_mix_and_appointment_status(): void
    {
        $user = User::factory()->create();
        $professional = User::factory()->create(['name' => 'Dra. Ana']);

        $this->seedPurchase(
            totalPrice: 200,
            purchasedAt: '2026-06-10',
            sessionsTotal: 1,
            sessionsUsed: 0,
            purchaseType: ClientTreatmentPurchase::TYPE_SINGLE,
        );
        $this->seedPurchase(
            totalPrice: 1200,
            purchasedAt: '2026-06-10',
            sessionsTotal: 10,
            sessionsUsed: 0,
            purchaseType: ClientTreatmentPurchase::TYPE_PACKAGE,
        );

        $completed = Appointment::create([
            'client_id' => $this->client->id,
            'user_id' => $professional->id,
            'scheduled_at' => '2026-06-10 10:00:00',
            'status' => Appointment::STATUS_COMPLETED,
        ]);
        $completed->syncTreatments([$this->treatment->id]);

        Appointment::create([
            'client_id' => $this->client->id,
            'user_id' => $professional->id,
            'scheduled_at' => '2026-06-11 10:00:00',
            'status' => Appointment::STATUS_CANCELLED,
        ])->syncTreatments([$this->treatment->id]);

        $this->actingAs($user)
            ->get('/reports?start_date=2026-06-01&end_date=2026-06-30')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sales_mix.total_count', 2)
                ->where('sales_mix.total_revenue', 1400)
                ->where('appointment_status.summary.total', 2)
                ->where('appointment_status.summary.completed', 1)
                ->where('appointment_status.summary.cancelled', 1)
                ->where('appointment_status.summary.completion_rate', 50)
                ->where('professional_productivity.0.professional_name', 'Dra. Ana')
                ->where('professional_productivity.0.completed', 1)
                ->where('professional_productivity.0.hours', 0.5));
    }

    public function test_reports_identify_inactive_clients_with_remaining_sessions(): void
    {
        $user = User::factory()->create();

        $this->seedPurchase(totalPrice: 1200, purchasedAt: now()->subMonths(3)->toDateString(), sessionsTotal: 10, sessionsUsed: 2);

        Appointment::create([
            'client_id' => $this->client->id,
            'user_id' => null,
            'scheduled_at' => now()->subDays(90),
            'status' => Appointment::STATUS_COMPLETED,
        ])->syncTreatments([$this->treatment->id]);

        $this->actingAs($user)
            ->get('/reports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('inactive_clients.summary.client_count', 1)
                ->where('inactive_clients.summary.sessions_remaining', 8)
                ->where('inactive_clients.items.0.client_name', 'Maria Silva'));
    }

    public function test_reports_include_peak_hours_anamnesis_and_notifications(): void
    {
        $user = User::factory()->create();

        $appointment = Appointment::create([
            'client_id' => $this->client->id,
            'user_id' => $user->id,
            'scheduled_at' => '2026-06-10 14:00:00',
            'status' => Appointment::STATUS_CONFIRMED,
        ]);
        $appointment->syncTreatments([$this->treatment->id]);

        AnamnesisInvitation::create([
            'client_id' => $this->client->id,
            'created_by_user_id' => $user->id,
            'token' => 'token-filled',
            'expires_at' => now()->addDays(7),
            'used_at' => now(),
            'created_at' => '2026-06-10 09:00:00',
            'updated_at' => '2026-06-10 09:00:00',
        ]);

        AnamnesisInvitation::create([
            'client_id' => $this->client->id,
            'created_by_user_id' => $user->id,
            'token' => 'token-expired',
            'expires_at' => now()->subDay(),
            'created_at' => '2026-06-10 10:00:00',
            'updated_at' => '2026-06-10 10:00:00',
        ]);

        $question = AnamnesisQuestion::create([
            'question' => 'Possui alergia?',
            'type' => 'select',
            'options' => ['Sim', 'Não'],
            'order' => 1,
            'is_active' => true,
            'is_required' => true,
        ]);

        app(AnamnesisRecordService::class)->create(
            $this->client,
            $user->id,
            [$question->id => 'Sim'],
        );

        NotificationLog::create([
            'client_id' => $this->client->id,
            'appointment_id' => $appointment->id,
            'channel' => 'whatsapp',
            'type' => 'reminder',
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => '2026-06-10 08:00:00',
            'updated_at' => '2026-06-10 08:00:00',
        ]);

        NotificationLog::create([
            'client_id' => $this->client->id,
            'channel' => 'email',
            'type' => 'reminder',
            'status' => 'failed',
            'error_message' => 'Mailbox unavailable',
            'created_at' => '2026-06-10 08:30:00',
            'updated_at' => '2026-06-10 08:30:00',
        ]);

        $this->actingAs($user)
            ->get('/reports?start_date=2026-06-01&end_date=2026-06-30')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('peak_hours.total', 1)
                ->where('peak_hours.busiest.count', 1)
                ->where('anamnesis.summary.invitations_sent', 2)
                ->where('anamnesis.summary.invitations_filled', 1)
                ->where('anamnesis.summary.invitations_expired', 1)
                ->where('anamnesis.summary.records_created', 1)
                ->where('anamnesis.question_stats.0.responses.0.label', 'Sim')
                ->where('notifications.summary.total', 2)
                ->where('notifications.summary.sent', 1)
                ->where('notifications.summary.failed', 1)
                ->where('notifications.summary.delivery_rate', 50));
    }

    public function test_authenticated_user_can_export_reports_pdf(): void
    {
        $user = User::factory()->create();

        $this->seedPurchase(totalPrice: 250, purchasedAt: now()->toDateString(), sessionsTotal: 5, sessionsUsed: 1);

        $response = $this->actingAs($user)
            ->get('/reports/pdf?start_date='.now()->startOfMonth()->toDateString().'&end_date='.now()->toDateString());

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_reports_include_payment_summary_with_card_fees(): void
    {
        $user = User::factory()->create();
        $date = '2026-06-20';

        $purchase = $this->seedPurchase(
            totalPrice: 1000,
            purchasedAt: $date,
            sessionsTotal: 10,
            sessionsUsed: 0,
        );

        $purchase->payments()->createMany([
            [
                'method' => 'pix',
                'amount' => 400,
                'installments' => null,
                'card_type' => null,
            ],
            [
                'method' => 'card',
                'amount' => 600,
                'installments' => 3,
                'card_type' => 'credit',
            ],
        ]);

        $this->actingAs($user)
            ->get("/reports?start_date={$date}&end_date={$date}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('payment_summary.gross_revenue', 1000)
                ->where('payment_summary.gross_from_payments', 1000)
                ->where('payment_summary.total_fees', 28.68)
                ->where('payment_summary.net_revenue', 971.32)
                ->where('payment_summary.channels.0.label', 'Cartão crédito 3x')
                ->where('payment_summary.channels.1.label', 'Pix'));
    }

    public function test_guest_cannot_view_reports(): void
    {
        $this->get('/reports')->assertRedirect('/login');
        $this->get('/reports/pdf')->assertRedirect('/login');
    }

    public function test_courtesy_purchases_do_not_affect_revenue_or_average_ticket(): void
    {
        $user = User::factory()->create();
        $date = '2026-06-15';

        $this->seedPurchase(
            totalPrice: 1200,
            purchasedAt: $date,
            sessionsTotal: 10,
            sessionsUsed: 0,
        );
        $this->seedPurchase(
            totalPrice: 0,
            purchasedAt: $date,
            sessionsTotal: 1,
            sessionsUsed: 0,
            purchaseType: ClientTreatmentPurchase::TYPE_SINGLE,
            isCourtesy: true,
            calculatedPrice: 200,
            unitPrice: 200,
        );

        $this->actingAs($user)
            ->get("/reports?start_date={$date}&end_date={$date}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.revenue', 1200)
                ->where('summary.paid_purchase_count', 1)
                ->where('summary.purchase_count', 1)
                ->where('summary.courtesy_count', 1)
                ->where('summary.courtesy_reference_value', 200)
                ->where('summary.courtesy_sessions', 1)
                ->where('summary.average_ticket', 1200)
                ->where('top_treatments.0.revenue', 1200)
                ->where('revenue_chart.purchases.0', 1)
                ->where('revenue_chart.courtesies.0', 1));
    }

    public function test_courtesy_unused_sessions_count_sessions_with_zero_estimated_value(): void
    {
        $user = User::factory()->create();

        $this->seedPurchase(
            totalPrice: 0,
            purchasedAt: now()->toDateString(),
            sessionsTotal: 4,
            sessionsUsed: 1,
            isCourtesy: true,
            calculatedPrice: 800,
            unitPrice: 800,
        );

        $this->actingAs($user)
            ->get('/reports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('unused_sessions.summary.total_sessions', 3)
                ->where('unused_sessions.summary.estimated_value', 0)
                ->where('unused_sessions.items.0.estimated_value', 0));
    }

    private function seedPurchase(
        float $totalPrice,
        string $purchasedAt,
        int $sessionsTotal,
        int $sessionsUsed,
        string $purchaseType = ClientTreatmentPurchase::TYPE_PACKAGE,
        bool $isCourtesy = false,
        ?float $calculatedPrice = null,
        ?float $unitPrice = null,
    ): ClientTreatmentPurchase {
        $purchase = ClientTreatmentPurchase::create([
            'client_id' => $this->client->id,
            'purchase_type' => $purchaseType,
            'calculated_price' => $calculatedPrice ?? $totalPrice,
            'total_price' => $totalPrice,
            'is_courtesy' => $isCourtesy,
            'purchased_at' => $purchasedAt,
        ]);

        ClientTreatmentPurchaseItem::create([
            'client_treatment_purchase_id' => $purchase->id,
            'treatment_id' => $this->treatment->id,
            'unit_price' => $unitPrice ?? $totalPrice,
            'sessions_total' => $sessionsTotal,
            'sessions_used' => $sessionsUsed,
            'combo_no_discount' => false,
        ]);

        return $purchase;
    }
}
