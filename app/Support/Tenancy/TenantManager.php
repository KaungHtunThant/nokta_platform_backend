<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

/**
 * Holds the current tenant for the request lifecycle. Bound as a singleton.
 * Resolved by the ResolveTenant middleware (from token claim or domain).
 */
final class TenantManager
{
    private ?int $tenantId = null;

    public function set(int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    public function forget(): void
    {
        $this->tenantId = null;
    }
}
