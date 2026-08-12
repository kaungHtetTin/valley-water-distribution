<?php

namespace App\Support\Tenancy;

use LogicException;

class OrganizationContext
{
    private ?int $organizationId = null;

    public function set(int $organizationId): void
    {
        if ($organizationId < 1) {
            throw new LogicException('Organization identifiers must be positive integers.');
        }

        $this->organizationId = $organizationId;
    }

    public function id(): int
    {
        return $this->organizationId
            ?? throw new LogicException('No organization has been resolved for this request.');
    }

    public function resolved(): bool
    {
        return $this->organizationId !== null;
    }
}
