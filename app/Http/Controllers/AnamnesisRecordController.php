<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnamnesisRecordRequest;
use App\Models\AnamnesisQuestion;
use App\Models\AnamnesisRecord;
use App\Models\Client;
use App\Services\AnamnesisRecordService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AnamnesisRecordController extends Controller
{
    public function create(Client $client): Response
    {
        return Inertia::render('anamnesis/records/create', [
            'client' => $client,
            'questions' => AnamnesisQuestion::activeOrdered()->get(),
        ]);
    }

    public function store(
        StoreAnamnesisRecordRequest $request,
        Client $client,
        AnamnesisRecordService $anamnesisRecordService,
    ): RedirectResponse {
        $anamnesisRecordService->create(
            $client,
            $request->user()->id,
            $request->validated('answers'),
            $request->validated('notes'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Anamnese registrada com sucesso.']);

        return to_route('clients.show', $client);
    }

    public function show(Client $client, AnamnesisRecord $anamnesisRecord): Response
    {
        abort_unless($anamnesisRecord->client_id === $client->id, 404);

        $anamnesisRecord->load(['answers.question', 'user']);

        return Inertia::render('anamnesis/records/show', [
            'client' => $client,
            'record' => $anamnesisRecord,
        ]);
    }
}
