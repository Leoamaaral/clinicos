<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderAnamnesisQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'questions' => ['required', 'array', 'min:1'],
            'questions.*' => ['integer', 'exists:anamnesis_questions,id'],
        ];
    }
}
