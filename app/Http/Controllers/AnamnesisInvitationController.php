<?php

namespace App\Http\Controllers;

use App\Models\AnamnesisInvitation;
use App\Models\Client;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AnamnesisInvitationController extends Controller
{
    public function store(
        Client $client,
        WhatsAppService $whatsAppService,
    ): RedirectResponse {
        $invitation = AnamnesisInvitation::createForClient($client, auth()->user());

        $url = route('anamnesis.public.show', ['token' => $invitation->token]);
        $sent = $whatsAppService->sendAnamnesisRequest($client, $url);

        if ($sent) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Link de anamnese enviado por WhatsApp para o cliente.',
            ]);
        } else {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => "Link gerado, mas não foi possível enviar o WhatsApp. Compartilhe manualmente: {$url}",
            ]);
        }

        return back();
    }
}
