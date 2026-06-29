<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnamnesisRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'terms_accepted' => ['accepted'],
            'notes' => ['nullable', 'string'],
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable'],
            'answers.*.value' => ['nullable', 'string', 'in:Sim,Não'],
            'answers.*.detail' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
