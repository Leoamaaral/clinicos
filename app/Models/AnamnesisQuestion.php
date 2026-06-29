<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnamnesisQuestion extends Model
{
    protected $fillable = [
        'question',
        'type',
        'options',
        'order',
        'is_active',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_active' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AnamnesisAnswer::class, 'question_id');
    }

    public static function activeOrdered()
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('id');
    }
}
