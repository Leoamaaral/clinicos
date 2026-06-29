<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'cpf' => ['required', 'string', 'size:14', Rule::unique('clients', 'cpf')->ignore($this->route('client'))],
            'birth_date' => ['required', 'date', 'before:today'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'cpf.unique' => 'Este CPF já está cadastrado.',
            'birth_date.before' => 'A data de nascimento deve ser anterior a hoje.',
        ];
    }
}
