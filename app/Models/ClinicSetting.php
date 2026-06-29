<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicSetting extends Model
{
    protected $fillable = [
        'clinic_name',
        'clinic_phone',
        'clinic_email',
        'whatsapp_days_before',
        'whatsapp_enabled',
        'whatsapp_message_template',
        'whatsapp_booking_enabled',
        'whatsapp_booking_message_template',
        'whatsapp_orientations_enabled',
        'whatsapp_orientations_message_template',
        'email_days_before',
        'email_enabled',
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_days_before' => 'integer',
            'whatsapp_enabled' => 'boolean',
            'whatsapp_booking_enabled' => 'boolean',
            'whatsapp_orientations_enabled' => 'boolean',
            'email_days_before' => 'integer',
            'email_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'clinic_name' => env('APP_NAME'),
            'whatsapp_days_before' => 1,
            'whatsapp_enabled' => true,
            'whatsapp_message_template' => 'Olá {nome}! Lembramos que seu tratamento "{tratamento}" está agendado para {data} às {hora}. Qualquer dúvida, entre em contato conosco.',
            'whatsapp_booking_enabled' => true,
            'whatsapp_booking_message_template' => 'Olá {nome}! Seu agendamento de "{tratamento}" foi confirmado para {data} às {hora} na {clinica}. Em caso de dúvidas, responda esta mensagem.',
            'whatsapp_orientations_enabled' => true,
            'whatsapp_orientations_message_template' => 'Olá {nome}! Seguem orientações importantes para seu atendimento na {clinica}: chegue com alguns minutos de antecedência, evite exposição solar intensa antes do procedimento e informe-nos sobre alergias ou medicamentos em uso.',
            'email_days_before' => 1,
            'email_enabled' => true,
        ]);
    }
}
