<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnamnesisRecordRequest;
use App\Models\AnamnesisInvitation;
use App\Models\AnamnesisQuestion;
use App\Models\ClinicSetting;
use App\Services\AnamnesisRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PublicAnamnesisController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = AnamnesisInvitation::query()
            ->with('client')
            ->where('token', $token)
            ->first();

        if (! $invitation || ! $invitation->isValid()) {
            return Inertia::render('anamnesis/public/invalid');
        }

        return Inertia::render('anamnesis/public/fill', [
            'clientName' => $invitation->client->name,
            'clinicName' => ClinicSetting::current()->clinic_name,
            'questions' => AnamnesisQuestion::activeOrdered()->get(),
            'token' => $token,
        ]);
    }

    public function store(
        StoreAnamnesisRecordRequest $request,
        string $token,
        AnamnesisRecordService $anamnesisRecordService,
    ): RedirectResponse {
        $invitation = AnamnesisInvitation::query()
            ->with('client')
            ->where('token', $token)
            ->first();

        if (! $invitation || ! $invitation->isValid()) {
            return to_route('anamnesis.public.show', ['token' => $token]);
        }

        DB::transaction(function () use ($request, $invitation, $anamnesisRecordService) {
            $record = $anamnesisRecordService->create(
                $invitation->client,
                null,
                $request->validated('answers'),
                $request->validated('notes'),
            );

            $invitation->markAsUsed($record);
        });

        return to_route('anamnesis.public.success');
    }

    public function success(): Response
    {
        return Inertia::render('anamnesis/public/success', [
            'clinicName' => ClinicSetting::current()->clinic_name,
        ]);
    }
}
