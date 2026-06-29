<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'client_id',
        'user_id',
        'scheduled_at',
        'scheduled_end_at',
        'status',
        'notes',
        'whatsapp_reminder_sent_at',
        'email_reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'whatsapp_reminder_sent_at' => 'datetime',
            'email_reminder_sent_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function appointmentTreatments(): HasMany
    {
        return $this->hasMany(AppointmentTreatment::class);
    }

    public function treatments(): BelongsToMany
    {
        return $this->belongsToMany(Treatment::class, 'appointment_treatments')
            ->withPivot('client_treatment_purchase_item_id')
            ->withTimestamps();
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @param  array<int>  $treatmentIds
     */
    /**
     * @param  array<int>  $treatmentIds
     */
    public static function createWithTreatments(array $attributes, array $treatmentIds): self
    {
        $appointment = self::create($attributes);
        $appointment->syncTreatments($treatmentIds);

        return $appointment->load('treatments');
    }

    /**
     * @param  array<int>  $treatmentIds
     */
    public function syncTreatments(array $treatmentIds): void
    {
        $treatmentIds = array_values(array_unique(array_map('intval', $treatmentIds)));

        $this->appointmentTreatments()
            ->whereNotIn('treatment_id', $treatmentIds)
            ->delete();

        $existingIds = $this->appointmentTreatments()->pluck('treatment_id')->all();

        foreach ($treatmentIds as $treatmentId) {
            if (! in_array($treatmentId, $existingIds, true)) {
                $this->appointmentTreatments()->create(['treatment_id' => $treatmentId]);
            }
        }
    }

    public function totalDurationMinutes(): int
    {
        $this->loadMissing('treatments');

        return (int) $this->treatments->sum('duration_minutes');
    }

    public function effectiveEndAt(): Carbon
    {
        if ($this->scheduled_end_at) {
            return Carbon::parse($this->scheduled_end_at);
        }

        $duration = $this->totalDurationMinutes() ?: 60;

        return Carbon::parse($this->scheduled_at)->addMinutes($duration);
    }

    public function bookedDurationMinutes(): int
    {
        if ($this->scheduled_end_at) {
            return (int) max(0, $this->scheduled_at->diffInMinutes($this->scheduled_end_at));
        }

        return $this->totalDurationMinutes() ?: 60;
    }

    public function treatmentNamesLabel(): string
    {
        $this->loadMissing('treatments');

        return $this->treatments->pluck('name')->join(', ');
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_SCHEDULED => 'Agendado',
            self::STATUS_CONFIRMED => 'Confirmado',
            self::STATUS_COMPLETED => 'Concluído',
            self::STATUS_CANCELLED => 'Cancelado',
        ];
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
