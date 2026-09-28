<?php

namespace Tests\Feature;

use App\Domain\Debt\Contracts\VehicleDebtProvider;
use App\Domain\Debt\Debt;
use App\Domain\Debt\Exceptions\ProviderUnavailableException;
use App\Domain\Debt\Money;
use App\Domain\Debt\ProviderDebtResponse;
use App\Infrastructure\Observability\SimpleMetricsRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ObservabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(SimpleMetricsRegistry::class)->reset();
    }

    public function test_generates_request_id_when_not_sent(): void
    {
        Http::fake([
            'http://provider-rest:8000/*' => Http::response([
                'plate' => 'ABC1234',
                'debts' => [],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => 'ABC1234',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('X-Request-ID');
        $requestId = $response->headers->get('X-Request-ID');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $requestId);
    }

    public function test_preserves_valid_incoming_request_id(): void
    {
        Http::fake([
            'http://provider-rest:8000/*' => Http::response([
                'plate' => 'ABC1234',
                'debts' => [],
            ], 200),
        ]);

        $customId = 'trace-id-abc-12345';
        $response = $this->withHeader('X-Request-ID', $customId)
            ->postJson('/api/v1/vehicles/debts', [
                'placa' => 'ABC1234',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('X-Request-ID', $customId);
    }

    public function test_replaces_malformed_request_id_with_uuid(): void
    {
        Http::fake([
            'http://provider-rest:8000/*' => Http::response([
                'plate' => 'ABC1234',
                'debts' => [],
            ], 200),
        ]);

        $malformedId = 'invalid id with spaces!@#$%^&*()';
        $response = $this->withHeader('X-Request-ID', $malformedId)
            ->postJson('/api/v1/vehicles/debts', [
                'placa' => 'ABC1234',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('X-Request-ID');
        $this->assertNotSame($malformedId, $response->headers->get('X-Request-ID'));
    }

    public function test_structured_logs_and_lgpd_plate_masking(): void
    {
        $loggedEvents = [];
        Log::listen(function ($message) use (&$loggedEvents) {
            $context = $message->context ?? [];
            if (isset($context['event'])) {
                $loggedEvents[] = [
                    'event' => $context['event'],
                    'context' => $context,
                    'message' => $message->message,
                ];
            }
        });

        Http::fake([
            'http://provider-rest:8000/*' => Http::response([
                'plate' => 'ABC1234',
                'debts' => [
                    [
                        'type' => 'IPVA',
                        'amount' => '500.00',
                        'due_date' => '2026-01-10',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => 'ABC1234',
        ]);

        $response->assertStatus(200);

        $eventNames = array_column($loggedEvents, 'event');
        $this->assertContains('request.received', $eventNames);
        $this->assertContains('provider.request', $eventNames);
        $this->assertContains('provider.response', $eventNames);
        $this->assertContains('vehicle_debt.completed', $eventNames);

        // LGPD Check: The unmasked plate 'ABC1234' must never appear in logged contexts
        foreach ($loggedEvents as $entry) {
            if (isset($entry['context']['plate'])) {
                $this->assertSame('ABC****', $entry['context']['plate']);
                $this->assertStringNotContainsString('1234', $entry['context']['plate']);
            }
        }
    }

    public function test_retry_and_fallback_events_logged_on_transient_failure(): void
    {
        $loggedEvents = [];
        Log::listen(function ($message) use (&$loggedEvents) {
            $context = $message->context ?? [];
            if (isset($context['event'])) {
                $loggedEvents[] = [
                    'event' => $context['event'],
                    'context' => $context,
                ];
            }
        });

        // Fail REST provider, succeed on SOAP provider
        Http::fake([
            'http://provider-rest:8000/*' => Http::response('Service Unavailable', 503),
            'http://provider-soap:8000/*' => Http::response(
                '<?xml version="1.0" encoding="UTF-8"?><response><debts></debts></response>',
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);

        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => 'ABC1234',
        ]);

        $response->assertStatus(200);

        $eventNames = array_column($loggedEvents, 'event');
        $this->assertContains('provider.retry', $eventNames);
        $this->assertContains('provider.fallback', $eventNames);

        // Find fallback event
        $fallbackEvent = null;
        foreach ($loggedEvents as $entry) {
            if ($entry['event'] === 'provider.fallback') {
                $fallbackEvent = $entry['context'];
                break;
            }
        }

        $this->assertNotNull($fallbackEvent);
        $this->assertSame('rest', $fallbackEvent['from']);
        $this->assertSame('soap', $fallbackEvent['to']);
        $this->assertNotEmpty($fallbackEvent['reason']);
    }

    public function test_all_providers_unavailable_logs_vehicle_debt_failed(): void
    {
        $loggedEvents = [];
        Log::listen(function ($message) use (&$loggedEvents) {
            $context = $message->context ?? [];
            if (isset($context['event'])) {
                $loggedEvents[] = [
                    'event' => $context['event'],
                    'context' => $context,
                ];
            }
        });

        Http::fake([
            'http://provider-rest:8000/*' => Http::response('Server Error', 500),
            'http://provider-soap:8000/*' => Http::response('Server Error', 500),
        ]);

        $response = $this->postJson('/api/v1/vehicles/debts', [
            'placa' => 'ABC1234',
        ]);

        $response->assertStatus(503);

        $failedEvents = array_filter($loggedEvents, fn ($e) => $e['event'] === 'vehicle_debt.failed');
        $this->assertNotEmpty($failedEvents);

        $lastFail = end($failedEvents)['context'];
        $this->assertSame('all_providers_unavailable', $lastFail['error_type']);
        $this->assertSame(503, $lastFail['status_code']);
    }

    public function test_metrics_endpoint_and_counters(): void
    {
        Http::fake([
            'http://provider-rest:8000/*' => Http::response([
                'plate' => 'ABC1234',
                'debts' => [],
            ], 200),
        ]);

        // Before query
        $metricsBefore = $this->getJson('/api/metrics')->json('metrics');
        $initialSuccess = $metricsBefore['vehicle_debt_success_total'] ?? 0;

        // Perform query
        $this->postJson('/api/v1/vehicles/debts', ['placa' => 'ABC1234']);

        // After query
        $response = $this->getJson('/api/metrics');
        $response->assertStatus(200);

        $metrics = $response->json('metrics');
        $this->assertArrayHasKey('vehicle_debt_requests_total', $metrics);
        $this->assertArrayHasKey('vehicle_debt_success_total', $metrics);
        $this->assertArrayHasKey('vehicle_debt_error_total', $metrics);
        $this->assertArrayHasKey('provider_requests_total', $metrics);
        $this->assertArrayHasKey('provider_failures_total', $metrics);
        $this->assertArrayHasKey('provider_retries_total', $metrics);
        $this->assertArrayHasKey('provider_fallbacks_total', $metrics);

        $this->assertGreaterThan($initialSuccess, $metrics['vehicle_debt_success_total']);
    }

    public function test_health_vs_health_integrations_endpoint(): void
    {
        // GET /api/health tests only monolith application
        $healthResponse = $this->getJson('/api/health');
        $healthResponse->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'service' => 'monolith',
            ]);

        // GET /api/health/integrations checks external dependencies
        Http::fake([
            'http://provider-rest:8000/*' => Http::response(['status' => 'ok'], 200),
            'http://provider-soap:8000/*' => Http::response(['status' => 'ok'], 200),
            'http://payment-provider:8000/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $integrationsResponse = $this->getJson('/api/health/integrations');
        $integrationsResponse->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'service' => 'monolith',
            ]);
    }
}
