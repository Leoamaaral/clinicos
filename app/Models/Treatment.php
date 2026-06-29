<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Treatment extends Model
{
    protected $fillable = [
        'name',
        'description',
        'single_price',
        'package_price',
        'package_6_price',
        'duration_minutes',
        'image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'single_price' => 'decimal:2',
            'package_price' => 'decimal:2',
            'package_6_price' => 'decimal:2',
            'duration_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected $appends = ['image_url'];

    public function appointments(): BelongsToMany
    {
        return $this->belongsToMany(Appointment::class, 'appointment_treatments')
            ->withPivot('client_treatment_purchase_item_id')
            ->withTimestamps();
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(ClientTreatmentPurchaseItem::class);
    }

    public function packagePriceForSessions(int $sessions): float
    {
        return match ($sessions) {
            6 => (float) $this->package_6_price,
            10 => (float) $this->package_price,
            default => throw new \InvalidArgumentException("Pacote de {$sessions} sessões não suportado."),
        };
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return '/storage/'.$this->image_path;
    }
}
