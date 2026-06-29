<?php

namespace App\Mail;

use App\Models\Appointment;
use App\Models\ClinicSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Appointment $appointment,
    ) {}

    public function envelope(): Envelope
    {
        $settings = ClinicSetting::current();

        return new Envelope(
            subject: 'Lembrete de tratamento - '.$settings->clinic_name,
        );
    }

    public function content(): Content
    {
        $settings = ClinicSetting::current();

        return new Content(
            markdown: 'emails.appointment-reminder',
            with: [
                'appointment' => $this->appointment,
                'client' => $this->appointment->client,
                'treatmentNames' => $this->appointment->treatmentNamesLabel(),
                'settings' => $settings,
            ],
        );
    }
}
