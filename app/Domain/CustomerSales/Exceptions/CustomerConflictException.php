<?php

namespace App\Domain\CustomerSales\Exceptions;

use RuntimeException;

class CustomerConflictException extends RuntimeException
{
    public function __construct(string $message, public readonly string $conflictCode = 'stale_version')
    {
        parent::__construct($message);
    }
}
