<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class VehicleDebtRequest extends FormRequest
{
    /**
     * Regex for Brazilian vehicle license plates:
     * - Traditional standard: ABC1234 (3 letters, 4 numbers)
     * - Mercosul standard: ABC1D23 (3 letters, 1 number, 1 letter/number, 2 numbers)
     */
    private const string PLATE_REGEX = '/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$|^[A-Z]{3}[0-9]{4}$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare inputs for validation (clean whitespace and uppercase).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('placa')) {
            $this->merge([
                'placa' => strtoupper(trim((string) $this->input('placa'))),
            ]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'placa' => ['required', 'string', 'regex:' . self::PLATE_REGEX],
            'provider' => ['nullable', 'string', 'in:rest,soap,ai,csv'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'placa.required' => 'A placa do veiculo e obrigatoria.',
            'placa.regex' => 'A placa do veiculo informada e invalida.',
            'placa.string' => 'A placa do veiculo e obrigatoria.',
            'provider.in' => 'O provedor informado e invalido. Provedores permitidos: rest, soap, ai, csv.',
        ];
    }

    /**
     * Returns the normalized plate.
     */
    public function getPlate(): string
    {
        return strtoupper(trim((string) $this->input('placa')));
    }

    /**
     * Returns custom provider order if specified.
     *
     * @return array<int, string>|null
     */
    public function getCustomOrder(): ?array
    {
        if ($this->filled('provider')) {
            return [strtolower(trim((string) $this->input('provider')))];
        }

        return null;
    }

    /**
     * Configure the validator instance to check for unknown/unrecognized fields.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $allowedKeys = ['placa', 'provider'];
            $unknownKeys = array_diff(array_keys($this->all()), $allowedKeys);

            if (! empty($unknownKeys)) {
                $validator->errors()->add('unknown_fields', implode(', ', $unknownKeys));
            }
        });
    }

    /**
     * Handle a failed validation attempt with an HTTP 400 response.
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();
        $rawPlate = (string) $this->input('placa', '');

        // 1. Rejeição de campos desconhecidos (P1)
        if ($errors->has('unknown_fields')) {
            $unknownFields = explode(', ', (string) $errors->first('unknown_fields'));

            \App\Infrastructure\Observability\SimpleMetricsRegistry::increment('vehicle_debt_error_total', 1, [
                'error_type' => 'unknown_field',
            ]);

            \Illuminate\Support\Facades\Log::warning('vehicle_debt.failed', [
                'event' => 'vehicle_debt.failed',
                'plate' => \App\Application\Support\PlateMasker::mask($rawPlate),
                'error_type' => 'unknown_field',
                'status_code' => 400,
                'reason' => 'Campos desconhecidos no payload: ' . implode(', ', $unknownFields),
            ]);

            throw new HttpResponseException(
                response()->json([
                    'error' => 'unknown_field',
                    'unrecognized_fields' => $unknownFields,
                ], 400)
            );
        }

        // 2. Erro de formato de placa inválida (P0: estritamente {"error": "invalid_plate"})
        if ($errors->has('placa')) {
            $hasPlacaInput = $this->has('placa') && trim((string) $this->input('placa')) !== '';

            if ($hasPlacaInput) {
                \App\Infrastructure\Observability\SimpleMetricsRegistry::increment('vehicle_debt_error_total', 1, [
                    'error_type' => 'invalid_plate',
                ]);

                \Illuminate\Support\Facades\Log::warning('vehicle_debt.failed', [
                    'event' => 'vehicle_debt.failed',
                    'plate' => \App\Application\Support\PlateMasker::mask($rawPlate),
                    'error_type' => 'invalid_plate',
                    'status_code' => 400,
                    'reason' => 'invalid_plate',
                ]);

                throw new HttpResponseException(
                    response()->json([
                        'error' => 'invalid_plate',
                    ], 400)
                );
            }
        }

        // 3. Demais validações (placa ausente, provider inválido)
        $message = $errors->first('placa') ?: $errors->first('provider') ?: 'Parametros invalidos.';

        \App\Infrastructure\Observability\SimpleMetricsRegistry::increment('vehicle_debt_error_total', 1, [
            'error_type' => 'validation_error',
        ]);

        \Illuminate\Support\Facades\Log::warning('vehicle_debt.failed', [
            'event' => 'vehicle_debt.failed',
            'plate' => \App\Application\Support\PlateMasker::mask($rawPlate),
            'error_type' => 'validation_error',
            'status_code' => 400,
            'reason' => $message,
        ]);

        throw new HttpResponseException(
            response()->json([
                'error' => $message,
            ], 400)
        );
    }
}
