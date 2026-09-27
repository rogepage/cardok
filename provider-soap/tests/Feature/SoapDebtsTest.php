<?php

namespace Tests\Feature;

use Tests\TestCase;

class SoapDebtsTest extends TestCase
{
    public function test_returns_debts_for_vehicle_with_debts(): void
    {
        $xmlPayload = '<request><plate>ABC1234</plate></request>';

        $response = $this->call(
            'POST',
            '/soap',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/xml'],
            $xmlPayload
        );

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $content = $response->getContent();
        $this->assertStringContainsString('<plate>ABC1234</plate>', $content);
        $this->assertStringContainsString('<debts>', $content);
        $this->assertStringContainsString('<category>IPVA</category>', $content);
        $this->assertStringContainsString('<value>1500.00</value>', $content);
        $this->assertStringContainsString('<expiration>2024-01-10</expiration>', $content);
        $this->assertStringContainsString('<category>MULTA</category>', $content);
        $this->assertStringContainsString('<value>300.50</value>', $content);
        $this->assertStringContainsString('<expiration>2024-02-15</expiration>', $content);
    }

    public function test_returns_self_closing_debts_tag_for_vehicle_without_debts(): void
    {
        $xmlPayload = '<request><plate>DEF5678</plate></request>';

        $response = $this->call(
            'POST',
            '/soap',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/xml'],
            $xmlPayload
        );

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $content = $response->getContent();
        $this->assertStringContainsString('<plate>DEF5678</plate>', $content);
        $this->assertStringContainsString('<debts/>', $content);
        $this->assertStringNotContainsString('<debts></debts>', $content);
        $this->assertDoesNotMatchRegularExpression('/<debts>\s*<\/debts>/', $content);
    }

    public function test_returns_http_500_when_provider_mode_is_error(): void
    {
        config(['services.provider_mode' => 'error']);

        $xmlPayload = '<request><plate>ABC1234</plate></request>';

        $response = $this->call(
            'POST',
            '/soap',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/xml'],
            $xmlPayload
        );

        $response->assertStatus(500);
    }

    public function test_returns_invalid_payload_when_provider_mode_is_invalid_response(): void
    {
        config(['services.provider_mode' => 'invalid_response']);

        $xmlPayload = '<request><plate>ABC1234</plate></request>';

        $response = $this->call(
            'POST',
            '/soap',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/xml'],
            $xmlPayload
        );

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringContainsString('<invalid>', $content);
        $this->assertStringNotContainsString('<plate>', $content);
        $this->assertStringNotContainsString('<debts', $content);
    }

    public function test_health_check_endpoint_is_working(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200)
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'provider-soap',
            ]);
    }
}
