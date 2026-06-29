<?php

namespace Tests\Unit;

use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    public function test_normalize_phone_adds_brazil_country_code(): void
    {
        $service = new WhatsAppService;

        $this->assertSame('5541988017557', $service->normalizePhone('(41) 98801-7557'));
        $this->assertSame('5511999999999', $service->normalizePhone('5511999999999'));
    }

    public function test_phones_match_with_or_without_brazilian_ninth_digit(): void
    {
        $service = new WhatsAppService;

        $this->assertTrue($service->phonesMatch('(41) 98801-7557', '554188017557'));
        $this->assertTrue($service->phonesMatch('5541988017557', '554188017557'));
        $this->assertSame('5541988017557', $service->canonicalizePhone('554188017557'));
        $this->assertSame('5541988017557', $service->canonicalizePhone('(41) 98801-7557'));
    }

    public function test_send_uses_z_api_format(): void
    {
        config([
            'services.whatsapp.api_url' => 'https://api.z-api.io/instances/test/token/secret/send-text',
            'services.whatsapp.client_token' => 'client-token-xyz',
        ]);

        Http::fake([
            'api.z-api.io/*' => Http::response(['messageId' => 'abc123'], 200),
        ]);

        $service = new WhatsAppService;
        $result = $service->send('(41) 98801-7557', 'Olá, teste!');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), 'send-text')
                && $request->hasHeader('Client-Token', 'client-token-xyz')
                && $request->hasHeader('Content-Type', 'application/json')
                && ($body['phone'] ?? null) === '5541988017557'
                && ($body['message'] ?? null) === 'Olá, teste!';
        });
    }

    public function test_send_simulates_when_api_url_is_missing(): void
    {
        config([
            'services.whatsapp.api_url' => null,
            'services.whatsapp.client_token' => null,
        ]);

        Http::fake();

        $service = new WhatsAppService;

        $this->assertTrue($service->send('41988017557', 'Teste'));

        Http::assertNothingSent();
    }

    public function test_send_fails_when_client_token_is_missing(): void
    {
        config([
            'services.whatsapp.api_url' => 'https://api.z-api.io/instances/test/token/secret/send-text',
            'services.whatsapp.client_token' => null,
        ]);

        Http::fake();

        $service = new WhatsAppService;

        $this->assertFalse($service->send('41988017557', 'Teste'));

        Http::assertNothingSent();
    }

    public function test_send_button_list_uses_z_api_format(): void
    {
        config([
            'services.whatsapp.api_url' => 'https://api.z-api.io/instances/test/token/secret/send-text',
            'services.whatsapp.client_token' => 'client-token-xyz',
        ]);

        Http::fake([
            'api.z-api.io/*' => Http::response(['messageId' => 'abc123'], 200),
        ]);

        $service = new WhatsAppService;
        $result = $service->sendButtonList('(41) 98801-7557', 'Confirme seu horário', [
            ['id' => 'reminder_confirm_1', 'label' => 'Confirmar'],
            ['id' => 'reminder_reschedule_1', 'label' => 'Reagendar'],
        ]);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), 'send-button-list')
                && ($body['phone'] ?? null) === '5541988017557'
                && ($body['message'] ?? null) === 'Confirme seu horário'
                && ($body['buttonList']['buttons'][0]['label'] ?? null) === 'Confirmar'
                && ($body['buttonList']['buttons'][1]['label'] ?? null) === 'Reagendar';
        });
    }

    public function test_parse_reminder_button_id(): void
    {
        $service = new WhatsAppService;

        $this->assertSame(
            ['action' => 'confirm', 'appointment_id' => 42],
            $service->parseReminderButtonId('reminder_confirm_42'),
        );

        $this->assertSame(
            ['action' => 'reschedule', 'appointment_id' => 7],
            $service->parseReminderButtonId('reminder_reschedule_7'),
        );

        $this->assertNull($service->parseReminderButtonId('invalid'));
    }
}
