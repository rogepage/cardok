<?php

namespace App\Application\VehicleDebt;

use App\Application\Support\PlateMasker;
use App\Domain\Debt\Contracts\VehicleDebtProvider;
use App\Domain\Debt\Exceptions\InvalidProviderResponseException;
use App\Domain\Debt\Exceptions\ProviderUnavailableException;
use App\Domain\Debt\ProviderDebtResponse;
use App\Infrastructure\Observability\SimpleMetricsRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProviderExecutor
{
    /**
     * @var callable(int): void
     */
    private $sleeper;

    private SimpleMetricsRegistry $metricsRegistry;

    public function __construct(
        private readonly int $maxRetries = 2,
        private readonly int $initialBackoffMs = 100,
        ?callable $sleeper = null,
        ?SimpleMetricsRegistry $metricsRegistry = null,
    ) {
        $this->sleeper = $sleeper ?? (static fn (int $ms) => usleep($ms * 1000));
        $this->metricsRegistry = $metricsRegistry ?? (app()->bound(SimpleMetricsRegistry::class) ? app(SimpleMetricsRegistry::class) : new SimpleMetricsRegistry());
    }

    public function execute(string $providerKey, VehicleDebtProvider $provider, string $plate): ProviderDebtResponse
    {
        $totalAttempts = 1 + max(0, $this->maxRetries);

        for ($attempt = 1; $attempt <= $totalAttempts; $attempt++) {
            $startTime = microtime(true);

            Log::info('provider.request', [
                'event' => 'provider.request',
                'provider' => $providerKey,
                'operation' => 'get_debts',
                'plate' => PlateMasker::mask($plate),
                'attempt' => $attempt,
            ]);
            $this->metricsRegistry->increment('provider_requests_total');

            try {
                $response = $provider->getDebts($plate);
                $durationMs = (int) round((microtime(true) - $startTime) * 1000);

                Log::info('provider.response', [
                    'event' => 'provider.response',
                    'provider' => $providerKey,
                    'status' => 'success',
                    'duration_ms' => $durationMs,
                    'debts_count' => count($response->debts),
                ]);

                return $response;
            } catch (InvalidProviderResponseException $e) {
                // Non-retriable: Malformed or corrupted responses will not be fixed by retrying.
                // Fail fast to allow immediate fallback to the next provider.
                $durationMs = (int) round((microtime(true) - $startTime) * 1000);
                $this->metricsRegistry->increment('provider_failures_total');
                $this->logFailure($providerKey, $attempt, $e, $durationMs, $plate, isFatal: true);

                throw $e;
            } catch (ProviderUnavailableException $e) {
                $durationMs = (int) round((microtime(true) - $startTime) * 1000);
                $isLastAttempt = ($attempt >= $totalAttempts);
                $this->metricsRegistry->increment('provider_failures_total');

                $this->logFailure($providerKey, $attempt, $e, $durationMs, $plate, isFatal: $isLastAttempt);

                if ($isLastAttempt) {
                    throw $e;
                }

                $backoffMs = $attempt * $this->initialBackoffMs;

                Log::warning('provider.retry', [
                    'event' => 'provider.retry',
                    'provider' => $providerKey,
                    'attempt' => $attempt + 1,
                    'max_attempts' => $totalAttempts,
                    'reason' => $e->getMessage(),
                    'backoff_ms' => $backoffMs,
                ]);
                $this->metricsRegistry->increment('provider_retries_total');

                ($this->sleeper)($backoffMs);
            }
        }

        throw new ProviderUnavailableException("Provider [{$providerKey}] exhausted all [{$totalAttempts}] attempts.");
    }

    public static function maskPlate(string $plate): string
    {
        return PlateMasker::mask($plate);
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
            'plate' => PlateMasker::mask($plate),
            'is_fatal_for_provider' => $isFatal,
        ]);
    }
}
