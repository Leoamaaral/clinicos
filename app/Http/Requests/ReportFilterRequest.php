<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function startDate(): string
    {
        return $this->input('start_date', now()->startOfMonth()->toDateString());
    }

    public function endDate(): string
    {
        return $this->input('end_date', now()->toDateString());
    }
}
