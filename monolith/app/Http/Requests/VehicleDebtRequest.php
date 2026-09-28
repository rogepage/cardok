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
            'provider' => ['nullable', 'string', 'in:rest,soap'],
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
            'provider.in' => 'O provedor informado e invalido. Provedores permitidos: rest, soap.',
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
     * Handle a failed validation attempt with an HTTP 400 response.
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();
        $message = $errors->first('placa') ?: $errors->first('provider') ?: 'Parametros invalidos.';

        throw new HttpResponseException(
            response()->json([
                'error' => $message,
            ], 400)
        );
    }
}
