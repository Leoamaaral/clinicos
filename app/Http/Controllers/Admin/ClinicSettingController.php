<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateClinicSettingsRequest;
use App\Models\ClinicSetting;
use App\Models\NotificationLog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClinicSettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/settings/index', [
            'settings' => ClinicSetting::current(),
            'recentNotifications' => NotificationLog::with(['client', 'appointment.treatments'])
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function update(UpdateClinicSettingsRequest $request): RedirectResponse
    {
        ClinicSetting::current()->update([
            ...$request->validated(),
            'whatsapp_enabled' => $request->boolean('whatsapp_enabled'),
            'whatsapp_booking_enabled' => $request->boolean('whatsapp_booking_enabled'),
            'whatsapp_orientations_enabled' => $request->boolean('whatsapp_orientations_enabled'),
            'email_enabled' => $request->boolean('email_enabled'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Configurações salvas com sucesso.']);

        return to_route('admin.settings.edit');
    }
}
