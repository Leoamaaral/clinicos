<?php

namespace Tests\Feature;

use App\Models\AnamnesisInvitation;
use App\Models\AnamnesisQuestion;
use App\Models\Client;
use App\Models\ClinicSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnamnesisInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.api_url' => 'https://api.z-api.io/instances/test/token/secret/send-text',
            'services.whatsapp.client_token' => 'client-token',
        ]);

        Http::fake(['api.z-api.io/*' => Http::response(['messageId' => 'ok'], 200)]);

        ClinicSetting::current()->update(['whatsapp_enabled' => true]);
    }

    public function test_staff_can_request_anamnesis_and_send_whatsapp(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'name' => 'Leonardo Amaral',
            'phone' => '41988017557',
            'cpf' => '109.526.349-88',
            'birth_date' => '1997-11-30',
        ]);

        $response = $this->actingAs($user)->post("/clients/{$client->id}/anamnesis/request");

        $response->assertRedirect();

        $this->assertDatabaseHas('anamnesis_invitations', [
            'client_id' => $client->id,
            'created_by_user_id' => $user->id,
            'used_at' => null,
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'client_id' => $client->id,
            'channel' => 'whatsapp',
            'type' => 'anamnesis_request',
            'status' => 'sent',
        ]);

        Http::assertSent(function ($request) use ($client) {
            $body = $request->data();

            return str_contains($body['message'] ?? '', $client->name)
                && str_contains($body['message'] ?? '', '/anamnesis/fill/');
        });
    }

    public function test_client_can_fill_anamnesis_via_public_link(): void
    {
        $client = Client::create([
            'name' => 'Leonardo Amaral',
            'phone' => '41988017557',
            'cpf' => '109.526.349-88',
            'birth_date' => '1997-11-30',
        ]);

        $question = AnamnesisQuestion::create([
            'question' => 'Possui alguma alergia?',
            'type' => 'checkbox',
            'order' => 1,
            'is_active' => true,
            'is_required' => true,
        ]);

        $invitation = AnamnesisInvitation::createForClient($client, null);

        $this->get("/anamnesis/fill/{$invitation->token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('anamnesis/public/fill')
                ->where('clientName', $client->name)
                ->has('questions', 1));

        $response = $this->post("/anamnesis/fill/{$invitation->token}", [
            'answers' => [
                $question->id => 'Sim',
            ],
            'notes' => 'Nenhuma observação',
            'terms_accepted' => '1',
        ]);

        $response->assertRedirect(route('anamnesis.public.success'));

        $this->assertDatabaseHas('anamnesis_records', [
            'client_id' => $client->id,
            'user_id' => null,
            'notes' => 'Nenhuma observação',
        ]);

        $this->assertDatabaseHas('anamnesis_answers', [
            'question_id' => $question->id,
            'answer' => 'Sim',
        ]);

        $invitation->refresh();
        $this->assertNotNull($invitation->used_at);
        $this->assertNotNull($invitation->anamnesis_record_id);
    }

    public function test_checkbox_answer_can_include_optional_detail(): void
    {
        $client = Client::create([
            'name' => 'Leonardo Amaral',
            'phone' => '41988017557',
            'cpf' => '109.526.349-88',
            'birth_date' => '1997-11-30',
        ]);

        $question = AnamnesisQuestion::create([
            'question' => 'Tem alergia a algum cosmético/medicamento?',
            'type' => 'checkbox',
            'order' => 1,
            'is_active' => true,
            'is_required' => true,
        ]);

        $invitation = AnamnesisInvitation::createForClient($client, null);

        $this->post("/anamnesis/fill/{$invitation->token}", [
            'answers' => [
                $question->id => [
                    'value' => 'Sim',
                    'detail' => 'Dipirona',
                ],
            ],
            'terms_accepted' => '1',
        ])->assertRedirect(route('anamnesis.public.success'));

        $this->assertDatabaseHas('anamnesis_answers', [
            'question_id' => $question->id,
            'answer' => json_encode(['value' => 'Sim', 'detail' => 'Dipirona'], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function test_anamnesis_requires_terms_acceptance(): void
    {
        $client = Client::create([
            'name' => 'Leonardo Amaral',
            'phone' => '41988017557',
            'cpf' => '109.526.349-88',
            'birth_date' => '1997-11-30',
        ]);

        $question = AnamnesisQuestion::create([
            'question' => 'Está grávida?',
            'type' => 'checkbox',
            'order' => 1,
            'is_active' => true,
            'is_required' => true,
        ]);

        $invitation = AnamnesisInvitation::createForClient($client, null);

        $this->post("/anamnesis/fill/{$invitation->token}", [
            'answers' => [$question->id => 'Não'],
        ])->assertSessionHasErrors('terms_accepted');

        $this->assertDatabaseCount('anamnesis_records', 0);
    }

    public function test_used_link_shows_invalid_page(): void
    {
        $client = Client::create([
            'name' => 'Leonardo Amaral',
            'phone' => '41988017557',
            'cpf' => '109.526.349-88',
            'birth_date' => '1997-11-30',
        ]);

        $question = AnamnesisQuestion::create([
            'question' => 'Possui alguma alergia?',
            'type' => 'text',
            'order' => 1,
            'is_active' => true,
            'is_required' => false,
        ]);

        $invitation = AnamnesisInvitation::createForClient($client, null);

        $this->post("/anamnesis/fill/{$invitation->token}", [
            'answers' => [$question->id => 'Não'],
            'terms_accepted' => '1',
        ])->assertRedirect(route('anamnesis.public.success'));

        $this->get("/anamnesis/fill/{$invitation->token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('anamnesis/public/invalid'));

        $this->post("/anamnesis/fill/{$invitation->token}", [
            'answers' => [$question->id => 'Sim novamente'],
            'terms_accepted' => '1',
        ])->assertRedirect("/anamnesis/fill/{$invitation->token}");

        $this->assertDatabaseCount('anamnesis_records', 1);
    }

    public function test_expired_link_shows_invalid_page(): void
    {
        $client = Client::create([
            'name' => 'Leonardo Amaral',
            'phone' => '41988017557',
            'cpf' => '109.526.349-88',
            'birth_date' => '1997-11-30',
        ]);

        $invitation = AnamnesisInvitation::create([
            'client_id' => $client->id,
            'token' => 'expired-token',
            'expires_at' => now()->subDay(),
        ]);

        $this->get('/anamnesis/fill/expired-token')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('anamnesis/public/invalid'));
    }

    public function test_new_request_invalidates_previous_unused_links(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'name' => 'Leonardo Amaral',
            'phone' => '41988017557',
            'cpf' => '109.526.349-88',
            'birth_date' => '1997-11-30',
        ]);

        $first = AnamnesisInvitation::createForClient($client, $user);
        $firstToken = $first->token;

        $this->actingAs($user)->post("/clients/{$client->id}/anamnesis/request");

        $this->assertDatabaseMissing('anamnesis_invitations', [
            'token' => $firstToken,
        ]);
    }
}
