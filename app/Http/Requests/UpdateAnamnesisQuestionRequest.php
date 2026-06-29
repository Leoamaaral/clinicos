<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnamnesisQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:500'],
            'type' => ['required', Rule::in(['text', 'select', 'checkbox'])],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:255'],
            'is_active' => ['boolean'],
            'is_required' => ['boolean'],
        ];
    }
}
