<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'cpf',
        'birth_date',
        'notes',
        'whatsapp_orientations_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'whatsapp_orientations_sent_at' => 'datetime',
        ];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->orderByDesc('scheduled_at');
    }

    public function anamnesisRecords(): HasMany
    {
        return $this->hasMany(AnamnesisRecord::class)->orderByDesc('created_at');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function treatmentPurchases(): HasMany
    {
        return $this->hasMany(ClientTreatmentPurchase::class)->orderByDesc('purchased_at');
    }
}
