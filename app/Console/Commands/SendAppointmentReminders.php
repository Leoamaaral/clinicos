<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Envia lembretes de tratamentos agendados via WhatsApp e e-mail';

    public function handle(NotificationService $notificationService): int
    {
        $sent = $notificationService->sendAppointmentReminders();

        $this->info("Lembretes enviados: {$sent}");

        return self::SUCCESS;
    }
}
