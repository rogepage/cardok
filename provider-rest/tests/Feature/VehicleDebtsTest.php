<?php

namespace Tests\Feature;

use Tests\TestCase;

class VehicleDebtsTest extends TestCase
{
    public function test_returns_debts_for_vehicle_with_debts(): void
    {
        $response = $this->getJson('/api/v1/vehicles/ABC1234/debts');

        $response->assertStatus(200)
            ->assertExactJson([
                'vehicle' => 'ABC1234',
                'debts' => [
                    [
                        'type' => 'IPVA',
                        'amount' => 1500.00,
                        'due_date' => '2024-01-10',
                    ],
                    [
                        'type' => 'MULTA',
                        'amount' => 300.50,
                        'due_date' => '2024-02-15',
                    ],
                ],
            ]);
    }

    public function test_returns_empty_debts_list_for_vehicle_without_debts(): void
    {
        $response = $this->getJson('/api/v1/vehicles/DEF5678/debts');

        $response->assertStatus(200)
            ->assertExactJson([
                'vehicle' => 'DEF5678',
                'debts' => [],
            ]);
    }

    public function test_returns_http_500_when_provider_mode_is_error(): void
    {
        config(['services.provider_mode' => 'error']);

        $response = $this->getJson('/api/v1/vehicles/ABC1234/debts');

        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Simulated external provider error',
            ]);
    }

    public function test_returns_invalid_payload_when_provider_mode_is_invalid_response(): void
    {
        config(['services.provider_mode' => 'invalid_response']);

        $response = $this->getJson('/api/v1/vehicles/ABC1234/debts');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayNotHasKey('debts', $data);
        $this->assertArrayHasKey('corrupted_data', $data);
    }

    public function test_health_check_endpoint_is_working(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'provider-rest',
            ]);
    }
}
