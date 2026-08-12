<?php

namespace Tests\Unit;

use App\Support\Tenancy\OrganizationContext;
use LogicException;
use PHPUnit\Framework\TestCase;

class OrganizationContextTest extends TestCase
{
    public function test_it_requires_an_explicit_organization_before_reading(): void
    {
        $context = new OrganizationContext;

        $this->assertFalse($context->resolved());
        $this->expectException(LogicException::class);

        $context->id();
    }

    public function test_it_exposes_the_resolved_organization(): void
    {
        $context = new OrganizationContext;
        $context->set(42);

        $this->assertTrue($context->resolved());
        $this->assertSame(42, $context->id());
    }
}
