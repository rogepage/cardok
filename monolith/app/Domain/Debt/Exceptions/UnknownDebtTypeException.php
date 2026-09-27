<?php

namespace App\Domain\Debt\Exceptions;

use RuntimeException;
use Throwable;

class UnknownDebtTypeException extends RuntimeException
{
    public function __construct(
        private readonly string $debtType,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $msg = $message !== '' ? $message : "Unknown debt type: {$this->debtType}";
        parent::__construct($msg, $code, $previous);
    }

    public function getDebtType(): string
    {
        return $this->debtType;
    }
}
