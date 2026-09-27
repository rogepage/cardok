<?php

namespace App\Domain\Debt\Exceptions;

class InvalidProviderResponseException extends ProviderException
{
    public function __construct(string $message = 'Provider returned an invalid or unparseable response', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
