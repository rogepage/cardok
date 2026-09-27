<?php

namespace App\Application\VehicleDebt;

use App\Domain\Debt\Contracts\VehicleDebtProvider;
use App\Domain\Debt\Exceptions\InvalidProviderResponseException;
use App\Domain\Debt\Exceptions\ProviderUnavailableException;
use App\Domain\Debt\ProviderDebtResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProviderExecutor
{
    /**
     * @var callable(int): void
     */
    private $sleeper;

    public function __construct(
        private readonly int $maxRetries = 2,
        private readonly int $initialBackoffMs = 100,
        ?callable $sleeper = null,
    ) {
        $this->sleeper = $sleeper ?? (static fn (int $ms): int => usleep($ms * 1000));
    }

    public function execute(string $providerKey, VehicleDebtProvider $provider, string $plate): ProviderDebtResponse
    {
        $totalAttempts = 1 + max(0, $this->maxRetries);

        for ($attempt = 1; $attempt <= $totalAttempts; $attempt++) {
            $startTime = microtime(true);

            try {
                return $provider->getDebts($plate);
            } catch (InvalidProviderResponseException $e) {
                // Non-retriable: Malformed or corrupted responses will not be fixed by retrying.
                // Fail fast to allow immediate fallback to the next provider.
                $durationMs = (int) round((microtime(true) - $startTime) * 1000);
                $this->logFailure($providerKey, $attempt, $e, $durationMs, $plate, isFatal: true);

                throw $e;
            } catch (ProviderUnavailableException $e) {
                $durationMs = (int) round((microtime(true) - $startTime) * 1000);
                $isLastAttempt = ($attempt >= $totalAttempts);

                $this->logFailure($providerKey, $attempt, $e, $durationMs, $plate, isFatal: $isLastAttempt);

                if ($isLastAttempt) {
                    throw $e;
                }

                $backoffMs = $attempt * $this->initialBackoffMs;
                ($this->sleeper)($backoffMs);
            }
        }

        throw new ProviderUnavailableException("Provider [{$providerKey}] exhausted all [{$totalAttempts}] attempts.");
    }

    public static function maskPlate(string $plate): string
    {
        $clean = trim($plate);
        $len = strlen($clean);

        if ($len <= 3) {
            return str_repeat('*', $len);
        }

        return substr($clean, 0, 3) . str_repeat('*', $len - 3);
    }

    private function logFailure(
        string $providerKey,
        int $attempt,
        Throwable $exception,
        int $durationMs,
        string $plate,
        bool $isFatal
    ): void {
        Log::warning('External vehicle debt provider failed', [
            'event' => 'vehicle_provider_failed',
            'provider' => $providerKey,
            'attempt' => $attempt,
            'error' => $exception->getMessage(),
            'duration_ms' => $durationMs,
            'plate' => self::maskPlate($plate),
            'is_fatal_for_provider' => $isFatal,
        ]);
    }
}
