<?php

namespace Tests\Unit\Application\VehicleDebt;

use App\Application\VehicleDebt\ProviderResolver;
use App\Infrastructure\Providers\Rest\RestVehicleDebtProvider;
use App\Infrastructure\Providers\Soap\SoapVehicleDebtProvider;
use InvalidArgumentException;
use Tests\TestCase;

class ProviderResolverTest extends TestCase
{
    private ProviderResolver $resolver;
    private RestVehicleDebtProvider $restProvider;
    private SoapVehicleDebtProvider $soapProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restProvider = new RestVehicleDebtProvider('http://mock-rest');
        $this->soapProvider = new SoapVehicleDebtProvider('http://mock-soap');

        $this->resolver = new ProviderResolver(
            $this->restProvider,
            $this->soapProvider,
        );
    }

    public function test_resolves_rest_and_soap_providers(): void
    {
        $this->assertSame($this->restProvider, $this->resolver->resolve('rest'));
        $this->assertSame($this->restProvider, $this->resolver->resolve('REST'));
        $this->assertSame($this->soapProvider, $this->resolver->resolve('soap'));
        $this->assertSame($this->soapProvider, $this->resolver->resolve('SOAP'));
    }

    public function test_throws_exception_on_unknown_provider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown vehicle debt provider: unknown');

        $this->resolver->resolve('unknown');
    }

    public function test_returns_configured_order(): void
    {
        config(['services.providers.order' => ['soap', 'rest']]);
        $this->assertSame(['soap', 'rest'], $this->resolver->getConfiguredOrder());

        config(['services.providers.order' => ['rest', 'soap']]);
        $this->assertSame(['rest', 'soap'], $this->resolver->getConfiguredOrder());

        config(['services.providers.order' => 'soap,rest']);
        $this->assertSame(['soap', 'rest'], $this->resolver->getConfiguredOrder());
    }
}
