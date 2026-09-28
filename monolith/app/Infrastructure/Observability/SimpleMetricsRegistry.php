<?php

namespace App\Infrastructure\Observability;

use Illuminate\Support\Facades\Cache;

class SimpleMetricsRegistry
{
    private const string CACHE_PREFIX = 'cardok_metrics:';

    /**
     * @var array<string, int> In-memory fallback/fast buffer
     */
    private array $inMemoryCounters = [
        'vehicle_debt_requests_total' => 0,
        'vehicle_debt_success_total' => 0,
        'vehicle_debt_error_total' => 0,
        'provider_requests_total' => 0,
        'provider_failures_total' => 0,
        'provider_retries_total' => 0,
        'provider_fallbacks_total' => 0,
    ];

    public static function increment(string $metric, int $step = 1, array $tags = []): void
    {
        $instance = app()->bound(self::class) ? app(self::class) : (app()->instance(self::class, new self()));
        $instance->incrementCount($metric, $step, $tags);
    }

    /**
     * Increments the specified metric counter on the instance.
     *
     * @param array<string, string> $tags
     */
    public function incrementCount(string $metric, int $step = 1, array $tags = []): void
    {
        $this->inMemoryCounters[$metric] = ($this->inMemoryCounters[$metric] ?? 0) + $step;

        try {
            $key = self::CACHE_PREFIX . $metric;
            if (! Cache::has($key)) {
                Cache::forever($key, $step);
            } else {
                Cache::increment($key, $step);
            }
        } catch (\Throwable) {
            // Memory counter serves as reliable fallback if cache store is unavailable
        }
    }

    public function incrementInstance(string $metric, int $step = 1, array $tags = []): void
    {
        $this->incrementCount($metric, $step, $tags);
    }

    /**
     * Retrieves the current count for a metric.
     */
    public function get(string $metric): int
    {
        try {
            $cached = Cache::get(self::CACHE_PREFIX . $metric);
            if ($cached !== null) {
                return (int) $cached;
            }
        } catch (\Throwable) {
            // Ignore cache error
        }

        return $this->inMemoryCounters[$metric] ?? 0;
    }

    /**
     * Returns all metric counters as an associative array.
     *
     * @return array<string, int>
     */
    public function getAll(): array
    {
        $metrics = [];
        foreach (array_keys($this->inMemoryCounters) as $key) {
            $metrics[$key] = $this->get($key);
        }

        return $metrics;
    }

    /**
     * Resets all metric counters (useful for isolated tests).
     */
    public function reset(): void
    {
        foreach (array_keys($this->inMemoryCounters) as $key) {
            $this->inMemoryCounters[$key] = 0;
            try {
                Cache::forget(self::CACHE_PREFIX . $key);
            } catch (\Throwable) {
                // Ignore cache error
            }
        }
    }
}
