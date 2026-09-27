<?php

namespace App\Domain\Debt\Exceptions;

class ProviderUnavailableException extends ProviderException
{
    public function __construct(string $message = 'Provider is unavailable or timed out', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
