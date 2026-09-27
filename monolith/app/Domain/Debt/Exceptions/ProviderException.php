<?php

namespace App\Domain\Debt\Exceptions;

use RuntimeException;

class ProviderException extends RuntimeException
{
    public function __construct(string $message = 'Provider communication error', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
