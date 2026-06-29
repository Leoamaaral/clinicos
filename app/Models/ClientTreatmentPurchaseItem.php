<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientTreatmentPurchaseItem extends Model
{
    protected $fillable = [
        'client_treatment_purchase_id',
        'treatment_id',
        'unit_price',
        'sessions_total',
        'sessions_used',
        'combo_no_discount',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'sessions_total' => 'integer',
            'sessions_used' => 'integer',
            'combo_no_discount' => 'boolean',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(ClientTreatmentPurchase::class, 'client_treatment_purchase_id');
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function appointmentTreatments(): HasMany
    {
        return $this->hasMany(AppointmentTreatment::class, 'client_treatment_purchase_item_id');
    }

    public function sessionsRemaining(): int
    {
        return max(0, $this->sessions_total - $this->sessions_used);
    }
}
