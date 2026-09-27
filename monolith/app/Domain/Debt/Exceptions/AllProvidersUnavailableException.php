<?php

namespace App\Domain\Debt\Exceptions;

class AllProvidersUnavailableException extends ProviderException
{
    public function __construct(string $message = 'All configured vehicle debt providers are unavailable', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
