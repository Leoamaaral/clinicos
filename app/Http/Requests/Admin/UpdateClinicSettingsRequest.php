<?php

namespace App\Http\Requests\Admin;

use App\Models\ClinicSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClinicSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clinic_name' => ['required', 'string', 'max:255'],
            'clinic_phone' => ['nullable', 'string', 'max:20'],
            'clinic_email' => ['nullable', 'email', 'max:255'],
            'whatsapp_days_before' => ['required', 'integer', 'min:0', 'max:30'],
            'whatsapp_enabled' => ['boolean'],
            'whatsapp_message_template' => ['nullable', 'string'],
            'whatsapp_booking_enabled' => ['boolean'],
            'whatsapp_booking_message_template' => ['nullable', 'string'],
            'whatsapp_orientations_enabled' => ['boolean'],
            'whatsapp_orientations_message_template' => ['nullable', 'string'],
            'email_days_before' => ['required', 'integer', 'min:0', 'max:30'],
            'email_enabled' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $current = ClinicSetting::current();

        $preserveWhenMissing = [
            'email_days_before' => $current->email_days_before,
            'email_enabled' => $current->email_enabled,
            'whatsapp_booking_enabled' => $current->whatsapp_booking_enabled,
            'whatsapp_orientations_enabled' => $current->whatsapp_orientations_enabled,
            'whatsapp_orientations_message_template' => $current->whatsapp_orientations_message_template,
        ];

        foreach ($preserveWhenMissing as $field => $value) {
            if (! $this->has($field)) {
                $this->merge([$field => $value]);
            }
        }

        if (! $this->has('whatsapp_enabled')) {
            $this->merge(['whatsapp_enabled' => $current->whatsapp_enabled]);
        }
    }
}
