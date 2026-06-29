<?php

use App\Models\Appointment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dateTime('scheduled_end_at')->nullable()->after('scheduled_at');
        });

        Appointment::query()
            ->with('treatments')
            ->chunkById(100, function ($appointments) {
                foreach ($appointments as $appointment) {
                    $duration = $appointment->totalDurationMinutes() ?: 60;

                    $appointment->update([
                        'scheduled_end_at' => $appointment->scheduled_at->copy()->addMinutes($duration),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('scheduled_end_at');
        });
    }
};
