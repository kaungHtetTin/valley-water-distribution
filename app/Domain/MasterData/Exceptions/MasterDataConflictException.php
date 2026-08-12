<?php

namespace App\Domain\MasterData\Exceptions;

use RuntimeException;

class MasterDataConflictException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $conflictCode = 'stale_version',
    ) {
        parent::__construct($message);
    }
}
