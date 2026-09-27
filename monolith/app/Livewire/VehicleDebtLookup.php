<?php

namespace App\Livewire;

use App\Application\VehicleDebt\VehicleDebtService;
use App\Domain\Debt\Exceptions\AllProvidersUnavailableException;
use App\Domain\Debt\Exceptions\UnknownDebtTypeException;
use Livewire\Component;
use Throwable;

class VehicleDebtLookup extends Component
{
    public string $placa = '';

    /**
     * @var array{
     *     placa: string,
     *     debitos: array<int, array{tipo: string, valor_original: string, valor_atualizado: string, vencimento: string, dias_atraso: int}>,
     *     resumo: array{total_original: string, total_atualizado: string},
     *     pagamentos: array{opcoes: array<int, mixed>}
     * }|null
     */
    public ?array $resultado = null;

    public ?string $erro = null;

    /**
     * Brazilian plate regex (traditional ABC1234 or Mercosul ABC1D23).
     */
    private const string PLATE_REGEX = '/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$|^[A-Z]{3}[0-9]{4}$/';

    public function updatedPlaca(): void
    {
        $this->placa = strtoupper(trim($this->placa));
        $this->erro = null;
    }

    public function consultar(VehicleDebtService $vehicleDebtService): void
    {
        $this->erro = null;
        $this->resultado = null;

        $normalizedPlate = strtoupper(trim($this->placa));

        if ($normalizedPlate === '') {
            $this->erro = 'A placa do veículo é obrigatória.';
            return;
        }

        if (! preg_match(self::PLATE_REGEX, $normalizedPlate)) {
            $this->erro = 'Placa inválida. Verifique o formato informado.';
            return;
        }

        try {
            $consultation = $vehicleDebtService->consultDebts($normalizedPlate);
            $this->resultado = $consultation->toArray();
        } catch (AllProvidersUnavailableException) {
            $this->erro = 'Não foi possível consultar os débitos no momento. Tente novamente em alguns instantes.';
        } catch (UnknownDebtTypeException $e) {
            $this->erro = "O sistema encontrou um tipo de débito não reconhecido ({$e->getDebtType()}). Entre em contato com o suporte.";
        } catch (Throwable) {
            $this->erro = 'Ocorreu um erro inesperado ao consultar os débitos. Tente novamente em alguns instantes.';
        }
    }

    public function limpar(): void
    {
        $this->placa = '';
        $this->resultado = null;
        $this->erro = null;
    }

    public function render()
    {
        return view('livewire.vehicle-debt-lookup')
            ->layout('layouts.app', ['title' => 'Cardok — Consulta de Débitos Veiculares']);
    }
}
