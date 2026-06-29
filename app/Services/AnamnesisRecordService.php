<?php

namespace App\Services;

use App\Models\AnamnesisAnswer;
use App\Models\AnamnesisRecord;
use App\Models\Client;

class AnamnesisRecordService
{
    /**
     * @param  array<int|string, mixed>  $answers
     */
    public function create(Client $client, ?int $userId, array $answers, ?string $notes = null): AnamnesisRecord
    {
        $record = AnamnesisRecord::create([
            'client_id' => $client->id,
            'user_id' => $userId,
            'notes' => $notes,
        ]);

        foreach ($answers as $questionId => $answer) {
            AnamnesisAnswer::create([
                'anamnesis_record_id' => $record->id,
                'question_id' => $questionId,
                'answer' => $this->serializeAnswer($answer),
            ]);
        }

        return $record;
    }

    private function serializeAnswer(mixed $answer): ?string
    {
        if ($answer === null || $answer === '') {
            return null;
        }

        if (! is_array($answer)) {
            return (string) $answer;
        }

        $value = (string) ($answer['value'] ?? '');
        $detail = trim((string) ($answer['detail'] ?? ''));

        if ($detail !== '') {
            return json_encode(
                ['value' => $value, 'detail' => $detail],
                JSON_UNESCAPED_UNICODE,
            );
        }

        return $value !== '' ? $value : null;
    }
}
