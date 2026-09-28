<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HealthCheckIntegrationCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_subsequent_requests_within_ttl_hit_cache_and_avoid_external_http_calls(): void
    {
        $shouldFail = false;

        Http::fake([
            'http://provider-rest:8000/api/health' => function () use (&$shouldFail) {
                return $shouldFail
                    ? Http::response(['error' => 'should not be called'], 500)
                    : Http::response(['status' => 'ok', 'service' => 'provider-rest'], 200);
            },
            'http://provider-soap:8000/health' => Http::response(['status' => 'ok', 'service' => 'provider-soap'], 200),
            'http://payment-provider:8000/health' => Http::response(['status' => 'ok', 'service' => 'payment-provider'], 200),
        ]);

        // Request 1: Cache Miss -> probes external services, returns X-Cache: MISS
        $response1 = $this->get('/api/health/integrations');
        $response1->assertStatus(200)
            ->assertHeader('X-Cache', 'MISS')
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('services.provider-rest.status', 'ok');

        $this->assertSame(3, count(Http::recorded()));

        // Advance time within TTL (2 seconds)
        Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:02'));

        // If another HTTP call is attempted, it would fail with 500
        $shouldFail = true;

        // Request 2: Cache Hit -> returns cached 200, X-Cache: HIT without calling external services
        $response2 = $this->get('/api/health/integrations');
        $response2->assertStatus(200)
            ->assertHeader('X-Cache', 'HIT')
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('services.provider-rest.status', 'ok');

        $this->assertEquals($response1->json(), $response2->json());
        // No new HTTP calls were recorded on the second request
        $this->assertSame(3, count(Http::recorded()));
    }

    public function test_cache_expires_after_five_seconds_and_reprobes_external_providers(): void
    {
        $version = 1;

        Http::fake([
            'http://provider-rest:8000/api/health' => function () use (&$version) {
                return Http::response(['status' => 'ok', 'version' => $version], 200);
            },
            'http://provider-soap:8000/health' => Http::response(['status' => 'ok'], 200),
            'http://payment-provider:8000/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $response1 = $this->get('/api/health/integrations');
        $response1->assertStatus(200)
            ->assertHeader('X-Cache', 'MISS')
            ->assertJsonPath('services.provider-rest.data.version', 1);

        // Advance time past 5 seconds (6 seconds)
        Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:06'));
        $version = 2;

        // Request after expiry: Triggers fresh probing, returns X-Cache: MISS and updated version
        $response2 = $this->get('/api/health/integrations');
        $response2->assertStatus(200)
            ->assertHeader('X-Cache', 'MISS')
            ->assertJsonPath('services.provider-rest.data.version', 2);

        $this->assertSame(6, count(Http::recorded()));
    }

    public function test_cache_bypass_via_cache_control_no_cache_header(): void
    {
        $version = 1;

        Http::fake([
            'http://provider-rest:8000/api/health' => function () use (&$version) {
                return Http::response(['status' => 'ok', 'version' => $version], 200);
            },
            'http://provider-soap:8000/health' => Http::response(['status' => 'ok'], 200),
            'http://payment-provider:8000/health' => Http::response(['status' => 'ok'], 200),
        ]);

        // Initial request populates cache
        $this->get('/api/health/integrations')->assertStatus(200);

        // Advance only 1 second (within 5s TTL)
        Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:01'));
        $version = 2;

        // Normal request hits cache
        $cachedResponse = $this->get('/api/health/integrations');
        $cachedResponse->assertHeader('X-Cache', 'HIT')
            ->assertJsonPath('services.provider-rest.data.version', 1);

        // Request with Cache-Control: no-cache forces fresh probe
        $bypassResponse = $this->withHeader('Cache-Control', 'no-cache')->get('/api/health/integrations');
        $bypassResponse->assertStatus(200)
            ->assertHeader('X-Cache', 'MISS')
            ->assertJsonPath('services.provider-rest.data.version', 2);
    }

    public function test_degraded_state_is_cached_consistently_with_503_status(): void
    {
        $isFailing = true;

        Http::fake([
            'http://provider-rest:8000/api/health' => function () use (&$isFailing) {
                return $isFailing
                    ? Http::response(['error' => 'server down'], 500)
                    : Http::response(['status' => 'ok'], 200);
            },
            'http://provider-soap:8000/health' => Http::response(['status' => 'ok'], 200),
            'http://payment-provider:8000/health' => Http::response(['status' => 'ok'], 200),
        ]);

        // Request 1: Degraded -> returns 503, X-Cache: MISS
        $response1 = $this->get('/api/health/integrations');
        $response1->assertStatus(503)
            ->assertHeader('X-Cache', 'MISS')
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('services.provider-rest.status', 'error');

        // Within 5s, even if provider recovers, cached degraded status should be returned with X-Cache: HIT
        Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:03'));
        $isFailing = false;

        $response2 = $this->get('/api/health/integrations');
        $response2->assertStatus(503)
            ->assertHeader('X-Cache', 'HIT')
            ->assertJsonPath('status', 'degraded');
        $this->assertSame(3, count(Http::recorded()));

        // Past 5s TTL, fresh check reflects recovery
        Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:06'));

        $response3 = $this->get('/api/health/integrations');
        $response3->assertStatus(200)
            ->assertHeader('X-Cache', 'MISS')
            ->assertJsonPath('status', 'ok');
        $this->assertSame(6, count(Http::recorded()));
    }
}
