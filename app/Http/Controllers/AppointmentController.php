<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use App\Services\AppointmentAvailabilityService;
use App\Services\AppointmentSessionService;
use App\Services\ClientTreatmentAvailabilityService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsAppService,
        protected AppointmentSessionService $sessionService,
    ) {}

    public function index(Request $request): Response
    {
        $date = Carbon::parse($request->string('date')->toString() ?: now()->format('Y-m-d'));
        $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $date->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $appointments = Appointment::with(['client', 'treatments', 'professional'])
            ->whereBetween('scheduled_at', [$weekStart, $weekEnd])
            ->orderBy('scheduled_at')
            ->get();

        return Inertia::render('appointments/index', [
            'appointments' => $appointments,
            'filters' => [
                'date' => $date->format('Y-m-d'),
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
            ],
            'statusLabels' => Appointment::statusLabels(),
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'professionals' => User::staff()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function availableTreatments(
        Client $client,
        Request $request,
        ClientTreatmentAvailabilityService $availability,
    ): JsonResponse {
        $validated = $request->validate([
            'exclude_appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'include_treatment_id' => ['nullable', 'integer', 'exists:treatments,id'],
            'include_treatment_ids' => ['nullable', 'array'],
            'include_treatment_ids.*' => ['integer', 'exists:treatments,id'],
        ]);

        $alwaysInclude = collect($validated['include_treatment_ids'] ?? [])
            ->when(isset($validated['include_treatment_id']), fn ($c) => $c->push((int) $validated['include_treatment_id']))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return response()->json([
            'treatments' => $availability->getBookableTreatments(
                $client->id,
                isset($validated['exclude_appointment_id']) ? (int) $validated['exclude_appointment_id'] : null,
                $alwaysInclude,
            ),
        ]);
    }

    public function availableSlots(Request $request, AppointmentAvailabilityService $service): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'treatment_ids' => ['required', 'array', 'min:1'],
            'treatment_ids.*' => ['integer', 'exists:treatments,id'],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('role', User::ROLE_STAFF)],
            'exclude_appointment_id' => ['nullable', 'exists:appointments,id'],
        ]);

        $treatmentIds = array_values(array_unique(array_map('intval', $validated['treatment_ids'])));

        return response()->json(
            $service->getAvailabilityContext(
                $validated['date'],
                $treatmentIds,
                isset($validated['user_id']) ? (int) $validated['user_id'] : null,
                isset($validated['exclude_appointment_id']) ? (int) $validated['exclude_appointment_id'] : null,
            ),
        );
    }

    public function create(Request $request): Response
    {
        $date = $request->string('date')->toString() ?: now()->format('Y-m-d');

        if (Carbon::parse($date)->startOfDay()->lt(now()->startOfDay())) {
            $date = now()->format('Y-m-d');
        }

        return Inertia::render('appointments/create', [
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'professionals' => User::staff()->orderBy('name')->get(['id', 'name']),
            'prefill' => [
                'client_id' => $request->integer('client_id') ?: null,
                'date' => $date,
            ],
        ]);
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $treatmentIds = $validated['treatment_ids'];
        unset($validated['treatment_ids']);

        $userId = $validated['user_id'] ?? null;

        if (! $userId && $request->user()->isStaff()) {
            $userId = $request->user()->id;
        }

        $appointment = Appointment::create([
            ...$validated,
            'user_id' => $userId,
        ]);

        $appointment->syncTreatments($treatmentIds);

        $this->sessionService->syncAfterCreate($appointment);

        $this->whatsAppService->sendBookingConfirmation($appointment);
        $this->whatsAppService->sendBookingOrientations($appointment);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agendamento criado com sucesso.']);

        return to_route('appointments.index', ['date' => $request->date('scheduled_at')->format('Y-m-d')]);
    }

    public function edit(Appointment $appointment): Response
    {
        $appointment->load(['client', 'treatments', 'professional']);

        if ($appointment->professional?->isAdmin()) {
            $appointment->user_id = null;
        }

        return Inertia::render('appointments/edit', [
            'appointment' => $appointment,
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'professionals' => User::staff()->orderBy('name')->get(['id', 'name']),
            'statusLabels' => Appointment::statusLabels(),
        ]);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $previousStatus = $appointment->status;
        $previousTreatmentIds = $appointment->appointmentTreatments()->pluck('treatment_id')->sort()->values()->all();

        $validated = $request->validated();
        $treatmentIds = $validated['treatment_ids'];
        unset($validated['treatment_ids']);

        $appointment->update($validated);
        $appointment->syncTreatments($treatmentIds);

        $appointment = $appointment->fresh();

        $this->sessionService->syncAfterUpdate($appointment, $previousStatus);

        $newTreatmentIds = collect($treatmentIds)->sort()->values()->all();
        if ($previousTreatmentIds !== $newTreatmentIds) {
            $this->sessionService->syncAfterTreatmentChange($appointment);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agendamento atualizado com sucesso.']);

        return to_route('appointments.index', ['date' => $appointment->scheduled_at->format('Y-m-d')]);
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        $date = $appointment->scheduled_at->format('Y-m-d');

        $this->sessionService->releaseBeforeDelete($appointment);

        $appointment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agendamento removido com sucesso.']);

        return to_route('appointments.index', ['date' => $date]);
    }

    public function completeIndex(Request $request): Response
    {
        $date = Carbon::parse($request->string('date')->toString() ?: now()->format('Y-m-d'));
        $professionalId = $request->integer('professional_id') ?: null;

        $query = Appointment::with(['client', 'treatments', 'professional'])
            ->whereDate('scheduled_at', $date)
            ->where('status', '!=', Appointment::STATUS_CANCELLED)
            ->orderBy('scheduled_at');

        if ($professionalId) {
            $query->where('user_id', $professionalId);
        }

        $appointments = $query->get();
        $completedCount = $appointments->where('status', Appointment::STATUS_COMPLETED)->count();

        return Inertia::render('appointments/complete', [
            'appointments' => $appointments,
            'filters' => [
                'date' => $date->format('Y-m-d'),
                'professional_id' => $professionalId,
            ],
            'professionals' => User::staff()->orderBy('name')->get(['id', 'name']),
            'statusLabels' => Appointment::statusLabels(),
            'counts' => [
                'total' => $appointments->count(),
                'completed' => $completedCount,
                'pending' => $appointments->count() - $completedCount,
            ],
        ]);
    }

    public function complete(Appointment $appointment): RedirectResponse
    {
        if ($appointment->status === Appointment::STATUS_CANCELLED) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Agendamento cancelado não pode ser concluído.']);

            return redirect()->back();
        }

        if ($appointment->status === Appointment::STATUS_COMPLETED) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Agendamento já está concluído.']);

            return redirect()->back();
        }

        $previousStatus = $appointment->status;
        $appointment->update(['status' => Appointment::STATUS_COMPLETED]);
        $this->sessionService->syncAfterUpdate($appointment->fresh(), $previousStatus);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agendamento concluído com sucesso.']);

        return redirect()->back();
    }

    public function uncomplete(Appointment $appointment): RedirectResponse
    {
        if ($appointment->status === Appointment::STATUS_CANCELLED) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Agendamento cancelado não pode ser alterado.']);

            return redirect()->back();
        }

        if ($appointment->status !== Appointment::STATUS_COMPLETED) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Agendamento não está concluído.']);

            return redirect()->back();
        }

        $previousStatus = $appointment->status;
        $appointment->update(['status' => Appointment::STATUS_CONFIRMED]);
        $this->sessionService->syncAfterUpdate($appointment->fresh(), $previousStatus);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Conclusão desfeita com sucesso.']);

        return redirect()->back();
    }

    public function completeBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'professional_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', User::ROLE_STAFF)],
        ]);

        $query = Appointment::query()
            ->whereDate('scheduled_at', $validated['date'])
            ->whereIn('status', [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED]);

        if (! empty($validated['professional_id'])) {
            $query->where('user_id', $validated['professional_id']);
        }

        $count = 0;

        DB::transaction(function () use ($query, &$count) {
            foreach ($query->get() as $appointment) {
                $previousStatus = $appointment->status;
                $appointment->update(['status' => Appointment::STATUS_COMPLETED]);
                $this->sessionService->syncAfterUpdate($appointment->fresh(), $previousStatus);
                $count++;
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $count === 1
                ? '1 agendamento concluído com sucesso.'
                : "{$count} agendamentos concluídos com sucesso.",
        ]);

        $params = ['date' => Carbon::parse($validated['date'])->format('Y-m-d')];

        if (! empty($validated['professional_id'])) {
            $params['professional_id'] = $validated['professional_id'];
        }

        return to_route('appointments.complete.index', $params);
    }
}
