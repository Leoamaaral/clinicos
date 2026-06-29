<?php

namespace App\Services;

use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function __construct(
        protected WhatsAppService $whatsAppService,
    ) {}

    public function sendAppointmentReminders(): int
    {
        $settings = ClinicSetting::current();
        $sent = 0;

        if ($settings->whatsapp_enabled) {
            $sent += $this->sendWhatsAppReminders($settings);
        }

        if ($settings->email_enabled) {
            $sent += $this->sendEmailReminders($settings);
        }

        return $sent;
    }

    protected function sendWhatsAppReminders(ClinicSetting $settings): int
    {
        $targetDate = now()->addDays($settings->whatsapp_days_before)->startOfDay();
        $targetEnd = $targetDate->copy()->endOfDay();

        $appointments = Appointment::query()
            ->with(['client', 'treatments'])
            ->whereNull('whatsapp_reminder_sent_at')
            ->whereIn('status', [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED])
            ->whereBetween('scheduled_at', [$targetDate, $targetEnd])
            ->get();

        $sent = 0;

        foreach ($appointments as $appointment) {
            if ($this->whatsAppService->sendReminder($appointment)) {
                $sent++;
            }
        }

        return $sent;
    }

    protected function sendEmailReminders(ClinicSetting $settings): int
    {
        $targetDate = now()->addDays($settings->email_days_before)->startOfDay();
        $targetEnd = $targetDate->copy()->endOfDay();

        $appointments = Appointment::query()
            ->with(['client', 'treatments'])
            ->whereNull('email_reminder_sent_at')
            ->whereIn('status', [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED])
            ->whereBetween('scheduled_at', [$targetDate, $targetEnd])
            ->whereHas('client', fn ($q) => $q->whereNotNull('email'))
            ->get();

        $sent = 0;

        foreach ($appointments as $appointment) {
            try {
                Mail::to($appointment->client->email)->send(new AppointmentReminderMail($appointment));

                $appointment->update(['email_reminder_sent_at' => now()]);

                NotificationLog::create([
                    'appointment_id' => $appointment->id,
                    'client_id' => $appointment->client_id,
                    'channel' => 'email',
                    'type' => 'reminder',
                    'status' => 'sent',
                    'message' => 'Lembrete de tratamento enviado por e-mail',
                    'sent_at' => now(),
                ]);

                $sent++;
            } catch (\Throwable $e) {
                NotificationLog::create([
                    'appointment_id' => $appointment->id,
                    'client_id' => $appointment->client_id,
                    'channel' => 'email',
                    'type' => 'reminder',
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }
}
