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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
