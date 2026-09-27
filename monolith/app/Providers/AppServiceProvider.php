<?php

namespace App\Providers;

use App\Infrastructure\Providers\Rest\RestVehicleDebtProvider;
use App\Infrastructure\Providers\Soap\SoapVehicleDebtProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RestVehicleDebtProvider::class, function () {
            return new RestVehicleDebtProvider(
                baseUrl: (string) config('services.providers.rest_url', 'http://provider-rest:8000'),
                timeout: (int) config('services.providers.timeout', 2),
            );
        });

        $this->app->singleton(SoapVehicleDebtProvider::class, function () {
            return new SoapVehicleDebtProvider(
                baseUrl: (string) config('services.providers.soap_url', 'http://provider-soap:8000'),
                timeout: (int) config('services.providers.timeout', 2),
            );
        });

        $this->app->singleton(ProviderExecutor::class, function () {
            return new ProviderExecutor(
                maxRetries: (int) config('services.providers.retries', 2),
                initialBackoffMs: (int) config('services.providers.backoff_ms', 100),
            );
        });

        $this->app->singleton(ProviderResolver::class);
        $this->app->singleton(VehicleDebtService::class);

        $this->app->singleton(\App\Domain\Debt\Clock\ClockInterface::class, function () {
            return \App\Domain\Debt\Clock\FixedClock::fromIsoString('2024-05-10T00:00:00Z');
        });

        $this->app->singleton(\App\Domain\Debt\Policies\DebtInterestPolicyRegistry::class, function () {
            return new \App\Domain\Debt\Policies\DebtInterestPolicyRegistry([
                new \App\Domain\Debt\Policies\IpvaInterestPolicy(),
                new \App\Domain\Debt\Policies\MultaInterestPolicy(),
            ]);
        });

        $this->app->singleton(\App\Domain\Debt\Services\DebtCalculationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
