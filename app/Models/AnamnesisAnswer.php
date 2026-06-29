<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnamnesisAnswer extends Model
{
    protected $fillable = [
        'anamnesis_record_id',
        'question_id',
        'answer',
    ];

    protected $appends = [
        'formatted_answer',
    ];

    public function getFormattedAnswerAttribute(): ?string
    {
        if ($this->answer === null) {
            return null;
        }

        $decoded = json_decode($this->answer, true);

        if (is_array($decoded) && array_key_exists('value', $decoded)) {
            $value = (string) $decoded['value'];
            $detail = trim((string) ($decoded['detail'] ?? ''));

            return $detail !== '' ? "{$value} — {$detail}" : $value;
        }

        return $this->answer;
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(AnamnesisRecord::class, 'anamnesis_record_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AnamnesisQuestion::class, 'question_id');
    }
}
