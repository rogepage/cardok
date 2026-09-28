<?php

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' && ($uri === '/health' || $uri === '/health/')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'ok',
        'service' => 'payment-provider',
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit(0);
}

// Mock contract for future settlement evolution (NOT invoked by Cardok main consultation flow)
if ($method === 'POST' && ($uri === '/charge' || $uri === '/charge/')) {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: [];

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'approved',
        'transaction_id' => 'mock_tx_' . bin2hex(random_bytes(8)),
        'amount' => $data['amount'] ?? '0.00',
        'method' => $data['method'] ?? 'unknown',
        'simulated' => true,
        'message' => 'Simulated payment receipt. No real financial transaction was executed.',
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit(0);
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 'error',
    'message' => 'Not Found',
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
