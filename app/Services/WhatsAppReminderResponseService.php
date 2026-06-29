<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClinicSetting;
use App\Models\Client;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Log;

class WhatsAppReminderResponseService
{
    public function __construct(
        protected WhatsAppService $whatsAppService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        if (($payload['fromMe'] ?? false) === true) {
            return;
        }

        $phone = $payload['phone'] ?? null;

        if (! is_string($phone) || $phone === '') {
            return;
        }

        $action = $this->resolveAction($payload);

        if ($action === null) {
            return;
        }

        $appointment = $this->resolveAppointment($payload, $phone, $action);

        if ($appointment === null) {
            return;
        }

        if (! $this->phoneMatchesClient($phone, $appointment->client)) {
            Log::warning('WhatsApp reminder response ignored: phone mismatch', [
                'appointment_id' => $appointment->id,
                'phone' => $phone,
            ]);

            return;
        }

        match ($action) {
            WhatsAppService::REMINDER_BUTTON_CONFIRM => $this->confirmAppointment($appointment),
            WhatsAppService::REMINDER_BUTTON_RESCHEDULE => $this->requestReschedule($appointment),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveAction(array $payload): ?string
    {
        $buttonId = $payload['buttonsResponseMessage']['buttonId'] ?? null;

        if (is_string($buttonId) && $buttonId !== '') {
            $parsed = $this->whatsAppService->parseReminderButtonId($buttonId);

            return $parsed['action'] ?? null;
        }

        $text = $payload['text']['message']
            ?? $payload['buttonsResponseMessage']['message']
            ?? null;

        if (! is_string($text)) {
            return null;
        }

        return match (mb_strtolower(trim($text))) {
            'confirmar' => WhatsAppService::REMINDER_BUTTON_CONFIRM,
            'reagendar' => WhatsAppService::REMINDER_BUTTON_RESCHEDULE,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveAppointment(array $payload, string $phone, string $action): ?Appointment
    {
        $buttonId = $payload['buttonsResponseMessage']['buttonId'] ?? null;

        if (is_string($buttonId) && $buttonId !== '') {
            $parsed = $this->whatsAppService->parseReminderButtonId($buttonId);

            if ($parsed !== null) {
                return Appointment::query()
                    ->with(['client', 'treatments'])
                    ->find($parsed['appointment_id']);
            }
        }

        $client = $this->whatsAppService->findClientByPhone($phone);

        if ($client === null) {
            return null;
        }

        return $this->findPendingReminderAppointment($client, $action);
    }

    protected function findPendingReminderAppointment(Client $client, string $action): ?Appointment
    {
        $query = Appointment::query()
            ->with(['client', 'treatments'])
            ->where('client_id', $client->id)
            ->whereNotNull('whatsapp_reminder_sent_at')
            ->where('scheduled_at', '>=', now()->startOfDay());

        if ($action === WhatsAppService::REMINDER_BUTTON_CONFIRM) {
            $query->where('status', Appointment::STATUS_SCHEDULED);
        }

        return $query
            ->orderByDesc('whatsapp_reminder_sent_at')
            ->first();
    }

    protected function confirmAppointment(Appointment $appointment): void
    {
        if ($appointment->status === Appointment::STATUS_SCHEDULED) {
            $appointment->update(['status' => Appointment::STATUS_CONFIRMED]);
        }

        $appointment->loadMissing(['client', 'treatments', 'professional']);

        $settings = ClinicSetting::current();
        $template = trim($settings->whatsapp_booking_message_template ?? '')
            ?: 'Olá {nome}! Seu agendamento de "{tratamento}" foi confirmado para {data} às {hora} na {clinica}. Em caso de dúvidas, responda esta mensagem.';

        $message = $this->whatsAppService->buildMessageFromTemplate($appointment, $template);

        $this->sendFollowUp($appointment, 'reminder_confirm', $message);
    }

    protected function requestReschedule(Appointment $appointment): void
    {
        $message = "✨ Tudo bem, {$appointment->client->name}!\n\nRecebemos seu pedido de reagendamento. Em breve entraremos em contato para agendar um novo horário para você.";
        $this->sendFollowUp($appointment, 'reminder_reschedule', $message);
    }

    protected function sendFollowUp(Appointment $appointment, string $type, string $message): void
    {
        $success = $this->whatsAppService->send($appointment->client->phone, $message);

        NotificationLog::create([
            'appointment_id' => $appointment->id,
            'client_id' => $appointment->client_id,
            'channel' => 'whatsapp',
            'type' => $type,
            'status' => $success ? 'sent' : 'failed',
            'message' => $message,
            'error_message' => $success ? null : 'Falha ao enviar resposta do lembrete via Z-API',
            'sent_at' => $success ? now() : null,
        ]);
    }

    protected function phoneMatchesClient(string $phone, Client $client): bool
    {
        return $this->whatsAppService->phonesMatch($phone, $client->phone);
    }
}
