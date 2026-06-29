<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClinicSetting;
use App\Models\NotificationLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public const REMINDER_BUTTON_CONFIRM = 'confirm';

    public const REMINDER_BUTTON_RESCHEDULE = 'reschedule';

    /**
     * Envia mensagem via Z-API.
     *
     * @see https://developer.z-api.io/message/send-text
     */
    public function send(string $phone, string $message): bool
    {
        return $this->postToEndpoint('send-text', $phone, [
            'message' => $message,
        ]);
    }

    /**
     * Envia mensagem com botões de resposta rápida via Z-API.
     *
     * @param  array<int, array{id: string, label: string}>  $buttons
     *
     * @see https://developer.z-api.io/message/send-button-list
     */
    public function sendButtonList(string $phone, string $message, array $buttons): bool
    {
        if (trim($message) === '') {
            Log::error('Z-API WhatsApp: mensagem com botões não pode ser vazia.');

            return false;
        }

        return $this->postToEndpoint('send-button-list', $phone, [
            'message' => $message,
            'buttonList' => [
                'buttons' => array_map(
                    fn (array $button) => [
                        'id' => $button['id'],
                        'label' => $button['label'],
                    ],
                    $buttons
                ),
            ],
        ]);
    }

    public function reminderButtonId(string $action, int $appointmentId): string
    {
        return "reminder_{$action}_{$appointmentId}";
    }

    /**
     * @return array{action: string, appointment_id: int}|null
     */
    public function parseReminderButtonId(string $buttonId): ?array
    {
        if (! preg_match('/^reminder_(confirm|reschedule)_(\d+)$/', $buttonId, $matches)) {
            return null;
        }

        return [
            'action' => $matches[1],
            'appointment_id' => (int) $matches[2],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postToEndpoint(string $endpoint, string $phone, array $payload): bool
    {
        $apiUrl = $this->resolveEndpointUrl($endpoint);

        if (! $apiUrl) {
            Log::info('WhatsApp (simulado): '.$phone.' - '.json_encode($payload, JSON_UNESCAPED_UNICODE));

            return true;
        }

        $clientToken = config('services.whatsapp.client_token');

        if (! $clientToken) {
            Log::error('Z-API WhatsApp: WHATSAPP_CLIENT_TOKEN não configurado.');

            return false;
        }

        $response = $this->http()
            ->post($apiUrl, [
                'phone' => $this->normalizePhone($phone),
                ...$payload,
            ]);

        if ($response->successful()) {
            return true;
        }

        Log::error('Z-API WhatsApp error', [
            'phone' => $phone,
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    }

    protected function resolveEndpointUrl(string $endpoint): ?string
    {
        $apiUrl = config('services.whatsapp.api_url');

        if (! $apiUrl) {
            return null;
        }

        if (str_ends_with($apiUrl, '/send-text')) {
            return substr($apiUrl, 0, -strlen('/send-text')).'/'.$endpoint;
        }

        return rtrim($apiUrl, '/').'/'.$endpoint;
    }

    /**
     * Cliente HTTP com headers obrigatórios da Z-API em todas as requisições.
     *
     * @see https://developer.z-api.io/security/client-token
     */
    protected function http(): PendingRequest
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'Client-Token' => (string) config('services.whatsapp.client_token'),
        ]);
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (! str_starts_with($digits, '55')) {
            $digits = '55'.$digits;
        }

        return $digits;
    }

    /**
     * Normaliza celulares brasileiros para o formato com nono dígito (55 + DDD + 9 + número).
     */
    public function canonicalizePhone(string $phone): string
    {
        $digits = $this->normalizePhone($phone);

        if (! str_starts_with($digits, '55') || strlen($digits) < 12) {
            return $digits;
        }

        $local = substr($digits, 4);

        if (strlen($local) === 8 && in_array($local[0], ['8', '9'], true)) {
            return substr($digits, 0, 4).'9'.$local;
        }

        return $digits;
    }

    public function phonesMatch(string $phoneA, string $phoneB): bool
    {
        return $this->canonicalizePhone($phoneA) === $this->canonicalizePhone($phoneB);
    }

    public function findClientByPhone(string $phone): ?Client
    {
        return Client::query()
            ->get()
            ->first(fn (Client $client) => $this->phonesMatch($phone, $client->phone));
    }

    public function buildMessageFromTemplate(Appointment $appointment, string $template): string
    {
        $settings = ClinicSetting::current();
        $appointment->loadMissing(['client', 'treatments', 'professional']);

        $scheduledAt = $appointment->scheduled_at;

        return str_replace(
            ['{nome}', '{tratamento}', '{data}', '{hora}', '{clinica}', '{profissional}'],
            [
                $appointment->client->name,
                $appointment->treatmentNamesLabel(),
                $scheduledAt->format('d/m/Y'),
                $scheduledAt->format('H:i'),
                $settings->clinic_name,
                $appointment->professional?->name ?? 'A definir',
            ],
            $template
        );
    }

    public function buildReminderMessage(Appointment $appointment): string
    {
        $settings = ClinicSetting::current();
        $template = $settings->whatsapp_message_template
            ?? 'Olá {nome}! Seu tratamento {tratamento} está agendado para {data} às {hora}.';

        return $this->buildMessageFromTemplate($appointment, $template);
    }

    public function buildBookingMessage(Appointment $appointment): string
    {
        $settings = ClinicSetting::current();
        $template = $settings->whatsapp_booking_message_template ?? '';

        return $this->buildMessageFromTemplate($appointment, $template);
    }

    public function buildOrientationsMessage(Appointment $appointment): string
    {
        $settings = ClinicSetting::current();
        $template = $settings->whatsapp_orientations_message_template ?? '';

        return $this->buildMessageFromTemplate($appointment, $template);
    }

    public function sendBookingConfirmation(Appointment $appointment): bool
    {
        $settings = ClinicSetting::current();

        if (! $settings->whatsapp_enabled || ! $settings->whatsapp_booking_enabled) {
            return false;
        }

        $template = trim($settings->whatsapp_booking_message_template ?? '');

        if ($template === '') {
            return false;
        }

        $appointment->loadMissing(['client', 'treatments', 'professional']);
        $client = $appointment->client;
        $message = $this->buildBookingMessage($appointment);

        $success = $this->send($client->phone, $message);

        NotificationLog::create([
            'appointment_id' => $appointment->id,
            'client_id' => $client->id,
            'channel' => 'whatsapp',
            'type' => 'booking',
            'status' => $success ? 'sent' : 'failed',
            'message' => $message,
            'error_message' => $success ? null : 'Falha ao enviar confirmação via Z-API',
            'sent_at' => $success ? now() : null,
        ]);

        return $success;
    }

    public function sendBookingOrientations(Appointment $appointment): bool
    {
        $settings = ClinicSetting::current();

        if (! $settings->whatsapp_enabled || ! $settings->whatsapp_orientations_enabled) {
            return false;
        }

        $template = trim($settings->whatsapp_orientations_message_template ?? '');

        if ($template === '') {
            return false;
        }

        $appointment->loadMissing(['client', 'treatments', 'professional']);
        $client = $appointment->client;

        if ($client->whatsapp_orientations_sent_at !== null) {
            return false;
        }

        $message = $this->buildOrientationsMessage($appointment);

        $success = $this->send($client->phone, $message);

        NotificationLog::create([
            'appointment_id' => $appointment->id,
            'client_id' => $client->id,
            'channel' => 'whatsapp',
            'type' => 'orientations',
            'status' => $success ? 'sent' : 'failed',
            'message' => $message,
            'error_message' => $success ? null : 'Falha ao enviar orientações via Z-API',
            'sent_at' => $success ? now() : null,
        ]);

        if ($success) {
            $client->update(['whatsapp_orientations_sent_at' => now()]);
        }

        return $success;
    }

    public function sendReminder(Appointment $appointment): bool
    {
        $client = $appointment->client;
        $message = $this->buildReminderMessage($appointment);

        $success = $this->sendButtonList($client->phone, $message, [
            [
                'id' => $this->reminderButtonId(self::REMINDER_BUTTON_CONFIRM, $appointment->id),
                'label' => 'Confirmar',
            ],
            [
                'id' => $this->reminderButtonId(self::REMINDER_BUTTON_RESCHEDULE, $appointment->id),
                'label' => 'Reagendar',
            ],
        ]);

        NotificationLog::create([
            'appointment_id' => $appointment->id,
            'client_id' => $client->id,
            'channel' => 'whatsapp',
            'type' => 'reminder',
            'status' => $success ? 'sent' : 'failed',
            'message' => $message,
            'error_message' => $success ? null : 'Falha ao enviar lembrete com botões via Z-API',
            'sent_at' => $success ? now() : null,
        ]);

        if ($success) {
            $appointment->update(['whatsapp_reminder_sent_at' => now()]);
        }

        return $success;
    }

    public function sendAnamnesisRequest(Client $client, string $url): bool
    {
        $settings = ClinicSetting::current();

        if (! $settings->whatsapp_enabled) {
            return false;
        }

        $message = "Olá {$client->name}! Por favor, preencha sua ficha de anamnese através do link abaixo:\n\n{$url}\n\nEste link é pessoal e válido por tempo limitado.";

        $success = $this->send($client->phone, $message);

        NotificationLog::create([
            'client_id' => $client->id,
            'channel' => 'whatsapp',
            'type' => 'anamnesis_request',
            'status' => $success ? 'sent' : 'failed',
            'message' => $message,
            'error_message' => $success ? null : 'Falha ao enviar link de anamnese via Z-API',
            'sent_at' => $success ? now() : null,
        ]);

        return $success;
    }
}
