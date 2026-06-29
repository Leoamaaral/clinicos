<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientTreatmentPurchasePayment extends Model
{
    public const METHOD_CASH = 'cash';

    public const METHOD_PIX = 'pix';

    public const METHOD_CARD = 'card';

    public const CARD_TYPE_DEBIT = 'debit';

    public const CARD_TYPE_CREDIT = 'credit';

    protected $fillable = [
        'client_treatment_purchase_id',
        'method',
        'amount',
        'installments',
        'card_type',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'installments' => 'integer',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(ClientTreatmentPurchase::class, 'client_treatment_purchase_id');
    }

    public static function methodLabels(): array
    {
        return [
            self::METHOD_CASH => 'Dinheiro',
            self::METHOD_PIX => 'Pix',
            self::METHOD_CARD => 'Cartão',
        ];
    }

    public static function cardTypeLabels(): array
    {
        return [
            self::CARD_TYPE_DEBIT => 'Débito',
            self::CARD_TYPE_CREDIT => 'Crédito',
        ];
    }
}
