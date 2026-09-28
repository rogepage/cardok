<?php

namespace App\Infrastructure\Observability;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;

class StructuredJsonFormatter extends NormalizerFormatter
{
    /**
     * Formats a Monolog log record into a single-line structured JSON string.
     */
    public function format(LogRecord $record): string
    {
        $normalized = $this->normalizeRecord($record);
        $context = $normalized['context'] ?? [];
        unset($normalized['context'], $normalized['extra']);

        $requestId = $context['request_id']
            ?? (app()->bound('request_id') ? app('request_id') : null);

        $payload = [
            'timestamp' => $record->datetime->format('Y-m-d\TH:i:s.v\Z'),
            'level' => $record->level->getName(),
            'message' => $record->message,
            'event' => $context['event'] ?? $record->message,
            'request_id' => $requestId,
        ];

        foreach ($context as $key => $value) {
            if ($key !== 'request_id') {
                $payload[$key] = $value;
            }
        }

        // Filter out null values
        $payload = array_filter($payload, static fn ($v) => $v !== null);

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
