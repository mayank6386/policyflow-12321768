<?php

namespace App\Http\Requests;

use App\Services\PremiumCalculator;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StorePolicyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_request_id' => ['required', 'string', 'max:64'],
            'agent_id' => ['required', 'string', 'max:64'],
            'customer_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today', 'before_or_equal:trip_start'],
            'destination' => ['required', 'string', Rule::in(['DOMESTIC', 'INTERNATIONAL'])],
            'trip_start' => ['required', 'date'],
            'trip_end' => ['required', 'date', 'after_or_equal:trip_start'],
            'plan_code' => ['required', 'string', Rule::in(['BASIC', 'PLUS', 'PREMIUM'])],
            'sum_insured' => ['required', 'integer', Rule::in([100000, 300000, 500000])],
        ];
    }

    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function ($validator) {
            $data = $this->all();
            $calculationFields = [
                'date_of_birth',
                'trip_start',
                'trip_end',
                'plan_code',
                'destination',
                'sum_insured',
            ];

            foreach ($calculationFields as $field) {
                if ($validator->errors()->has($field) || ! isset($data[$field])) {
                    return;
                }
            }

            if (! is_string($data['date_of_birth'])
                || ! is_string($data['trip_start'])
                || ! is_string($data['trip_end'])
                || ! is_string($data['plan_code'])
                || ! is_string($data['destination'])
                || ! is_numeric($data['sum_insured'])) {
                return;
            }

            try {
                PremiumCalculator::calculate(
                    $data['date_of_birth'],
                    $data['trip_start'],
                    $data['trip_end'],
                    $data['plan_code'],
                    $data['destination'],
                    (int) $data['sum_insured'],
                );
            } catch (\InvalidArgumentException $exception) {
                $validator->errors()->add('premium', $exception->getMessage());
            }
        });
    }
}
