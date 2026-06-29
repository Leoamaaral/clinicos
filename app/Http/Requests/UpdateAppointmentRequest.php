<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\AppointmentAvailabilityService;
use App\Services\ClientTreatmentAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'treatment_ids' => ['required', 'array', 'min:1'],
            'treatment_ids.*' => ['integer', 'exists:treatments,id'],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('role', User::ROLE_STAFF)],
            'scheduled_at' => [
                'required',
                'date',
                Rule::when(
                    in_array($this->input('status'), ['scheduled', 'confirmed'], true),
                    ['after_or_equal:today', 'after:now'],
                ),
            ],
            'scheduled_end_at' => [
                Rule::requiredIf($this->input('status') !== 'cancelled'),
                'nullable',
                'date',
                'after:scheduled_at',
            ],
            'status' => ['required', Rule::in(['scheduled', 'confirmed', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->input('status') === 'cancelled') {
                return;
            }

            $treatmentIds = array_values(array_unique(array_map('intval', $this->input('treatment_ids', []))));

            $service = app(AppointmentAvailabilityService::class);
            $scheduledAt = Carbon::parse($this->input('scheduled_at'));
            $scheduledEndAt = Carbon::parse($this->input('scheduled_end_at'));
            $appointmentId = $this->route('appointment')?->id;

            if (! $service->isIntervalAvailable(
                $scheduledAt,
                $scheduledEndAt,
                $this->input('user_id') ? (int) $this->input('user_id') : null,
                $appointmentId,
            )) {
                $validator->errors()->add('scheduled_time', 'Este horário não está disponível para os tratamentos selecionados.');
            }

            if ($this->input('status') !== 'completed') {
                $availability = app(ClientTreatmentAvailabilityService::class);

                if (! $availability->canBookAll(
                    (int) $this->input('client_id'),
                    $treatmentIds,
                    $appointmentId,
                )) {
                    $validator->errors()->add(
                        'treatment_ids',
                        'O cliente não possui sessões disponíveis para um ou mais tratamentos selecionados.',
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'treatment_ids.required' => 'Selecione ao menos um tratamento.',
            'treatment_ids.min' => 'Selecione ao menos um tratamento.',
            'scheduled_at.after_or_equal' => 'Não é possível agendar em datas passadas.',
            'scheduled_at.after' => 'O horário deve ser futuro.',
            'scheduled_end_at.after' => 'O horário de término deve ser posterior ao início.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('scheduled_at') && $this->has('scheduled_time')) {
            $this->merge([
                'scheduled_at' => $this->input('scheduled_at').' '.$this->input('scheduled_time'),
            ]);
        }

        if ($this->has('scheduled_at') && $this->has('scheduled_end_time')) {
            $date = $this->input('scheduled_at');
            if (str_contains($date, ' ')) {
                $date = explode(' ', $date)[0];
            }

            $this->merge([
                'scheduled_end_at' => $date.' '.$this->input('scheduled_end_time'),
            ]);
        }

        if ($this->has('treatment_ids') && is_array($this->input('treatment_ids'))) {
            $this->merge([
                'treatment_ids' => array_values(array_unique(array_map('intval', $this->input('treatment_ids')))),
            ]);
        }
    }
}
