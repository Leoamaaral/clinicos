<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTreatmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'single_price' => ['required', 'numeric', 'min:0'],
            'package_6_price' => ['required', 'numeric', 'min:0'],
            'package_price' => ['required', 'numeric', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'image' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['boolean'],
        ];
    }
}
