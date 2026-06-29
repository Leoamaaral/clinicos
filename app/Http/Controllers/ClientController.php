<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientTreatmentPurchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $clients = Client::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('cpf', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('clients/index', [
            'clients' => $clients,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('clients/create');
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        Client::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cliente cadastrado com sucesso.']);

        return to_route('clients.index');
    }

    public function show(Client $client): Response
    {
        $client->load([
            'appointments.treatments',
            'appointments.professional',
            'anamnesisRecords.answers.question',
            'anamnesisRecords.user',
            'treatmentPurchases.items.treatment',
            'treatmentPurchases.payments',
        ]);

        $now = Carbon::now();

        $pastAppointments = $client->appointments
            ->filter(fn (Appointment $a) => $a->status === Appointment::STATUS_COMPLETED
                || ($a->status !== Appointment::STATUS_CANCELLED && $a->scheduled_at->lt($now)))
            ->values();

        $upcomingAppointments = $client->appointments
            ->filter(fn (Appointment $a) => in_array($a->status, [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED], true)
                && $a->scheduled_at->gte($now))
            ->values();

        return Inertia::render('clients/show', [
            'client' => $client,
            'pastAppointments' => $pastAppointments,
            'upcomingAppointments' => $upcomingAppointments,
            'statusLabels' => Appointment::statusLabels(),
            'purchaseTypeLabels' => ClientTreatmentPurchase::typeLabels(),
        ]);
    }

    public function edit(Client $client): Response
    {
        return Inertia::render('clients/edit', [
            'client' => $client,
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cliente atualizado com sucesso.']);

        return to_route('clients.show', $client);
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cliente removido com sucesso.']);

        return to_route('clients.index');
    }
}
