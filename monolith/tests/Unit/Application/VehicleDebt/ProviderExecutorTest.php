<?php

namespace Tests\Unit\Application\VehicleDebt;

use App\Application\VehicleDebt\ProviderExecutor;
use App\Domain\Debt\Contracts\VehicleDebtProvider;
use App\Domain\Debt\Exceptions\InvalidProviderResponseException;
use App\Domain\Debt\Exceptions\ProviderUnavailableException;
use App\Domain\Debt\ProviderDebtResponse;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProviderExecutorTest extends TestCase
{
    public function test_succeeds_on_first_attempt_without_retries(): void
    {
        $expectedResponse = new ProviderDebtResponse('ABC1234', []);
        $calls = 0;
        $sleepTimes = [];

        $mockProvider = $this->createMock(VehicleDebtProvider::class);
        $mockProvider->expects($this->once())
            ->method('getDebts')
            ->with('ABC1234')
            ->willReturnCallback(function () use (&$calls, $expectedResponse) {
                $calls++;
                return $expectedResponse;
            });

        $executor = new ProviderExecutor(
            maxRetries: 2,
            initialBackoffMs: 100,
            sleeper: function (int $ms) use (&$sleepTimes) {
                $sleepTimes[] = $ms;
            }
        );

        $result = $executor->execute('rest', $mockProvider, 'ABC1234');

        $this->assertSame($expectedResponse, $result);
        $this->assertSame(1, $calls);
        $this->assertEmpty($sleepTimes);
    }

    public function test_retries_on_provider_unavailable_and_succeeds(): void
    {
        $expectedResponse = new ProviderDebtResponse('ABC1234', []);
        $attempts = 0;
        $sleepTimes = [];

        $mockProvider = $this->createMock(VehicleDebtProvider::class);
        $mockProvider->expects($this->exactly(3))
            ->method('getDebts')
            ->willReturnCallback(function () use (&$attempts, $expectedResponse) {
                $attempts++;
                if ($attempts < 3) {
                    throw new ProviderUnavailableException('Temporary server error');
                }
                return $expectedResponse;
            });

        $executor = new ProviderExecutor(
            maxRetries: 2,
            initialBackoffMs: 100,
            sleeper: function (int $ms) use (&$sleepTimes) {
                $sleepTimes[] = $ms;
            }
        );

        $result = $executor->execute('rest', $mockProvider, 'ABC1234');

        $this->assertSame($expectedResponse, $result);
        $this->assertSame(3, $attempts);
        $this->assertSame([100, 200], $sleepTimes);
    }

    public function test_exhausts_retries_and_throws_provider_unavailable_exception(): void
    {
        $attempts = 0;
        $sleepTimes = [];

        $mockProvider = $this->createMock(VehicleDebtProvider::class);
        $mockProvider->expects($this->exactly(3))
            ->method('getDebts')
            ->willReturnCallback(function () use (&$attempts) {
                $attempts++;
                throw new ProviderUnavailableException('Persistent connection failure');
            });

        $executor = new ProviderExecutor(
            maxRetries: 2,
            initialBackoffMs: 100,
            sleeper: function (int $ms) use (&$sleepTimes) {
                $sleepTimes[] = $ms;
            }
        );

        $this->expectException(ProviderUnavailableException::class);

        try {
            $executor->execute('rest', $mockProvider, 'ABC1234');
        } finally {
            $this->assertSame(3, $attempts);
            $this->assertSame([100, 200], $sleepTimes);
        }
    }

    public function test_does_not_retry_on_invalid_provider_response(): void
    {
        $attempts = 0;
        $sleepTimes = [];

        $mockProvider = $this->createMock(VehicleDebtProvider::class);
        $mockProvider->expects($this->once())
            ->method('getDebts')
            ->willReturnCallback(function () use (&$attempts) {
                $attempts++;
                throw new InvalidProviderResponseException('Corrupted JSON structure');
            });

        $executor = new ProviderExecutor(
            maxRetries: 2,
            initialBackoffMs: 100,
            sleeper: function (int $ms) use (&$sleepTimes) {
                $sleepTimes[] = $ms;
            }
        );

        $this->expectException(InvalidProviderResponseException::class);

        try {
            $executor->execute('rest', $mockProvider, 'ABC1234');
        } finally {
            $this->assertSame(1, $attempts);
            $this->assertEmpty($sleepTimes);
        }
    }

    public function test_mask_plate_masks_trailing_characters(): void
    {
        $this->assertSame('ABC****', ProviderExecutor::maskPlate('ABC1234'));
        $this->assertSame('DEF****', ProviderExecutor::maskPlate('DEF5678'));
        $this->assertSame('***', ProviderExecutor::maskPlate('ABC'));
        $this->assertSame('**', ProviderExecutor::maskPlate('AB'));
    }
}
