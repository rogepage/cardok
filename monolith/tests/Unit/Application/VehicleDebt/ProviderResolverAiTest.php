<?php

namespace Tests\Unit\Application\VehicleDebt;

use App\Application\VehicleDebt\ProviderResolver;
use App\Domain\Debt\Contracts\VehicleDebtProvider;
use App\Infrastructure\Providers\Ai\AiVehicleDebtProvider;
use App\Infrastructure\Providers\Rest\RestVehicleDebtProvider;
use App\Infrastructure\Providers\Soap\SoapVehicleDebtProvider;
use Tests\TestCase;

class ProviderResolverAiTest extends TestCase
{
    public function test_resolves_ai_provider(): void
    {
        $aiMock = $this->createMock(VehicleDebtProvider::class);
        $resolver = new ProviderResolver([
            'rest' => $this->createMock(RestVehicleDebtProvider::class),
            'soap' => $this->createMock(SoapVehicleDebtProvider::class),
            'ai' => $aiMock,
        ]);

        $this->assertSame($aiMock, $resolver->resolve('ai'));
        $this->assertSame($aiMock, $resolver->resolve('AI'));
    }

    public function test_configured_order_supports_ai_first(): void
    {
        config(['services.providers.order' => ['ai', 'rest', 'soap']]);

        $resolver = new ProviderResolver([
            'rest' => $this->createMock(RestVehicleDebtProvider::class),
            'soap' => $this->createMock(SoapVehicleDebtProvider::class),
            'ai' => $this->createMock(AiVehicleDebtProvider::class),
        ]);

        $this->assertSame(['ai', 'rest', 'soap'], $resolver->getConfiguredOrder());
    }

    public function test_configured_order_supports_ai_as_fallback(): void
    {
        config(['services.providers.order' => ['rest', 'soap', 'ai']]);

        $resolver = new ProviderResolver([
            'rest' => $this->createMock(RestVehicleDebtProvider::class),
            'soap' => $this->createMock(SoapVehicleDebtProvider::class),
            'ai' => $this->createMock(AiVehicleDebtProvider::class),
        ]);

        $this->assertSame(['rest', 'soap', 'ai'], $resolver->getConfiguredOrder());
    }

    public function test_resolves_csv_provider(): void
    {
        $csvMock = $this->createMock(VehicleDebtProvider::class);
        $resolver = new ProviderResolver([
            'rest' => $this->createMock(RestVehicleDebtProvider::class),
            'soap' => $this->createMock(SoapVehicleDebtProvider::class),
            'csv' => $csvMock,
        ]);

        $this->assertSame($csvMock, $resolver->resolve('csv'));
        $this->assertSame($csvMock, $resolver->resolve('CSV'));
    }

    public function test_configured_order_supports_csv(): void
    {
        config(['services.providers.order' => ['rest', 'soap', 'csv']]);

        $resolver = new ProviderResolver([
            'rest' => $this->createMock(RestVehicleDebtProvider::class),
            'soap' => $this->createMock(SoapVehicleDebtProvider::class),
            'csv' => $this->createMock(AiVehicleDebtProvider::class),
        ]);

        $this->assertSame(['rest', 'soap', 'csv'], $resolver->getConfiguredOrder());
    }
}
