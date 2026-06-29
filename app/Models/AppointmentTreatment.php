<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentTreatment extends Model
{
    protected $fillable = [
        'appointment_id',
        'treatment_id',
        'client_treatment_purchase_item_id',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(ClientTreatmentPurchaseItem::class, 'client_treatment_purchase_item_id');
    }
}
