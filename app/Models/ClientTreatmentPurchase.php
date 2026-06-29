<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientTreatmentPurchase extends Model
{
    public const TYPE_SINGLE = 'single';

    public const TYPE_PACKAGE = 'package';

    public const TYPE_PACKAGE_6 = 'package_6';

    public const TYPE_COMBO_SINGLE = 'combo_single';

    public const TYPE_COMBO_PACKAGE = 'combo_package';

    public const TYPE_COMBO_PACKAGE_6 = 'combo_package_6';

    protected $fillable = [
        'client_id',
        'purchase_type',
        'calculated_price',
        'discount_percent',
        'total_price',
        'is_courtesy',
        'purchased_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'calculated_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'total_price' => 'decimal:2',
            'is_courtesy' => 'boolean',
            'purchased_at' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClientTreatmentPurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ClientTreatmentPurchasePayment::class);
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_SINGLE => 'Sessão avulsa',
            self::TYPE_PACKAGE_6 => 'Pacote 6 sessões',
            self::TYPE_PACKAGE => 'Pacote 10 sessões',
            self::TYPE_COMBO_SINGLE => 'Combo (avulso)',
            self::TYPE_COMBO_PACKAGE_6 => 'Combo pacote 6 sessões',
            self::TYPE_COMBO_PACKAGE => 'Combo pacote 10 sessões',
        ];
    }

    public function deletionBlockReason(): ?string
    {
        if ($this->items()->where('sessions_used', '>', 0)->exists()) {
            return 'Esta compra possui sessões já utilizadas e não pode ser removida.';
        }

        $treatmentIds = $this->items()->pluck('treatment_id');

        if ($treatmentIds->isEmpty()) {
            return null;
        }

        $hasActiveAppointments = Appointment::query()
            ->where('client_id', $this->client_id)
            ->whereIn('status', [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED])
            ->whereHas('appointmentTreatments', fn ($q) => $q->whereIn('treatment_id', $treatmentIds))
            ->exists();

        if ($hasActiveAppointments) {
            return 'Existem agendamentos ativos para tratamentos desta compra. Cancele-os antes de remover.';
        }

        return null;
    }
}
