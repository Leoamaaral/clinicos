<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientTreatmentPurchase;
use App\Models\ClientTreatmentPurchaseItem;
use App\Models\Treatment;
use App\Models\User;
use App\Services\AppointmentAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTimeRangeTest extends TestCase
{
    use RefreshDatabase;

    private function assignTreatmentSessions(Client $client, Treatment $treatment, int $sessions = 10): void
    {
        $purchase = ClientTreatmentPurchase::create([
            'client_id' => $client->id,
            'purchase_type' => ClientTreatmentPurchase::TYPE_PACKAGE,
            'total_price' => $treatment->package_price,
            'purchased_at' => now(),
        ]);

        ClientTreatmentPurchaseItem::create([
            'client_treatment_purchase_id' => $purchase->id,
            'treatment_id' => $treatment->id,
            'unit_price' => $treatment->package_price,
            'sessions_total' => $sessions,
            'sessions_used' => 0,
        ]);
    }

    private function createClientAndTreatment(int $durationMinutes = 60): array
    {
        $client = Client::create([
            'name' => 'Maria Silva',
            'phone' => '41988017557',
            'cpf' => '123.456.789-00',
            'birth_date' => '1990-01-15',
        ]);

        $treatment = Treatment::create([
            'name' => 'Limpeza de pele',
            'single_price' => 150,
            'package_price' => 1200,
            'duration_minutes' => $durationMinutes,
            'is_active' => true,
        ]);

        $this->assignTreatmentSessions($client, $treatment, 10);

        return [$client, $treatment];
    }

    public function test_available_slots_returns_busy_intervals_and_clinic_hours(): void
    {
        $user = User::factory()->create();
        [$client, $treatment] = $this->createClientAndTreatment(90);

        $scheduledAt = now()->addDay()->setTime(10, 0);
        $scheduledEndAt = $scheduledAt->copy()->addMinutes(90);

        Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => $scheduledAt,
            'scheduled_end_at' => $scheduledEndAt,
            'status' => Appointment::STATUS_SCHEDULED,
        ], [$treatment->id]);

        $date = $scheduledAt->format('Y-m-d');

        $this->actingAs($user)
            ->getJson('/appointments/available-slots?'.http_build_query([
                'date' => $date,
                'treatment_ids' => [$treatment->id],
            ]))
            ->assertOk()
            ->assertJsonPath('clinic_open', '08:00')
            ->assertJsonPath('clinic_close', '22:00')
            ->assertJsonPath('max_duration_minutes', 90)
            ->assertJsonPath('busy_intervals.0.start', '10:00')
            ->assertJsonPath('busy_intervals.0.end', '11:30');
    }

    public function test_accepts_valid_interval_within_clinic_hours(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$client, $treatment] = $this->createClientAndTreatment(60);

        $date = now()->addDay()->format('Y-m-d');

        $this->actingAs($admin)
            ->post('/appointments', [
                'client_id' => $client->id,
                'treatment_ids' => [$treatment->id],
                'scheduled_at' => $date,
                'scheduled_time' => '14:00',
                'scheduled_end_time' => '15:00',
                'status' => 'scheduled',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('appointments', [
            'client_id' => $client->id,
        ]);

        $appointment = Appointment::query()->where('client_id', $client->id)->first();
        $this->assertSame('14:00', $appointment->scheduled_at->format('H:i'));
        $this->assertSame('15:00', $appointment->scheduled_end_at->format('H:i'));
    }

    public function test_accepts_shorter_duration_than_treatment(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$client, $treatment] = $this->createClientAndTreatment(120);

        $date = now()->addDay()->format('Y-m-d');

        $this->actingAs($admin)
            ->post('/appointments', [
                'client_id' => $client->id,
                'treatment_ids' => [$treatment->id],
                'scheduled_at' => $date,
                'scheduled_time' => '09:00',
                'scheduled_end_time' => '09:45',
                'status' => 'scheduled',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $appointment = Appointment::query()->where('client_id', $client->id)->first();
        $this->assertSame(45, $appointment->bookedDurationMinutes());
    }

    public function test_accepts_longer_duration_than_treatment_when_no_conflict(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$client, $treatment] = $this->createClientAndTreatment(60);

        $date = now()->addDay()->format('Y-m-d');

        $this->actingAs($admin)
            ->post('/appointments', [
                'client_id' => $client->id,
                'treatment_ids' => [$treatment->id],
                'scheduled_at' => $date,
                'scheduled_time' => '10:00',
                'scheduled_end_time' => '12:30',
                'status' => 'scheduled',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $appointment = Appointment::query()->where('client_id', $client->id)->first();
        $this->assertSame(150, $appointment->bookedDurationMinutes());
    }

    public function test_rejects_overlapping_interval(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$client, $treatment] = $this->createClientAndTreatment(60);

        $date = now()->addDay();
        $scheduledAt = $date->copy()->setTime(10, 0);

        Appointment::createWithTreatments([
            'client_id' => $client->id,
            'scheduled_at' => $scheduledAt,
            'scheduled_end_at' => $scheduledAt->copy()->addHour(),
            'status' => Appointment::STATUS_SCHEDULED,
        ], [$treatment->id]);

        $this->assignTreatmentSessions($client, $treatment, 5);

        $this->actingAs($admin)
            ->post('/appointments', [
                'client_id' => $client->id,
                'treatment_ids' => [$treatment->id],
                'scheduled_at' => $date->format('Y-m-d'),
                'scheduled_time' => '10:30',
                'scheduled_end_time' => '11:30',
                'status' => 'scheduled',
            ])
            ->assertSessionHasErrors('scheduled_time');
    }

    public function test_rejects_end_before_start(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$client, $treatment] = $this->createClientAndTreatment();

        $date = now()->addDay()->format('Y-m-d');

        $this->actingAs($admin)
            ->post('/appointments', [
                'client_id' => $client->id,
                'treatment_ids' => [$treatment->id],
                'scheduled_at' => $date,
                'scheduled_time' => '15:00',
                'scheduled_end_time' => '14:00',
                'status' => 'scheduled',
            ])
            ->assertSessionHasErrors('scheduled_end_at');
    }

    public function test_rejects_interval_outside_clinic_hours(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$client, $treatment] = $this->createClientAndTreatment();

        $date = now()->addDay()->format('Y-m-d');

        $this->actingAs($admin)
            ->post('/appointments', [
                'client_id' => $client->id,
                'treatment_ids' => [$treatment->id],
                'scheduled_at' => $date,
                'scheduled_time' => '21:00',
                'scheduled_end_time' => '22:30',
                'status' => 'scheduled',
            ])
            ->assertSessionHasErrors('scheduled_time');
    }

    public function test_is_interval_available_respects_clinic_close_at_22(): void
    {
        $service = app(AppointmentAvailabilityService::class);
        $date = now()->addDay()->format('Y-m-d');

        $start = Carbon::parse($date.' 21:00');
        $end = Carbon::parse($date.' 22:00');

        $this->assertTrue($service->isIntervalAvailable($start, $end));

        $endTooLate = Carbon::parse($date.' 22:01');
        $this->assertFalse($service->isIntervalAvailable($start, $endTooLate));
    }
}
