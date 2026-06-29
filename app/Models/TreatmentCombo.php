<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreatmentCombo extends Model
{
    protected $fillable = [
        'name',
        'sessions_count',
        'min_treatment_count',
        'extra_discount_percent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sessions_count' => 'integer',
            'min_treatment_count' => 'integer',
            'extra_discount_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public static function activeDefault(): ?self
    {
        return static::activeForSessions(10);
    }

    public static function activeForSessions(int $sessions): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->where('sessions_count', $sessions)
            ->orderBy('id')
            ->first();
    }
}
