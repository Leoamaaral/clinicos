<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AnamnesisInvitation extends Model
{
    protected $fillable = [
        'client_id',
        'created_by_user_id',
        'anamnesis_record_id',
        'token',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function anamnesisRecord(): BelongsTo
    {
        return $this->belongsTo(AnamnesisRecord::class);
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public function markAsUsed(AnamnesisRecord $record): void
    {
        $this->update([
            'used_at' => now(),
            'anamnesis_record_id' => $record->id,
        ]);
    }

    public static function createForClient(Client $client, ?User $createdBy, int $expiresInDays = 7): self
    {
        static::query()
            ->where('client_id', $client->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->delete();

        return static::create([
            'client_id' => $client->id,
            'created_by_user_id' => $createdBy?->id,
            'token' => Str::uuid()->toString(),
            'expires_at' => now()->addDays($expiresInDays),
        ]);
    }
}
