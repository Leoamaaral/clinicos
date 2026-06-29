<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Treatment;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = now()->startOfDay();
        $weekEnd = now()->addDays(7)->endOfDay();

        return Inertia::render('dashboard', [
            'stats' => [
                'clients' => Client::count(),
                'appointments_today' => Appointment::whereDate('scheduled_at', today())->count(),
                'appointments_week' => Appointment::whereBetween('scheduled_at', [$today, $weekEnd])->count(),
                'treatments' => Treatment::where('is_active', true)->count(),
            ],
            'upcomingAppointments' => Appointment::with(['client', 'treatments', 'professional'])
                ->where('scheduled_at', '>=', now())
                ->whereIn('status', [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED])
                ->orderBy('scheduled_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
