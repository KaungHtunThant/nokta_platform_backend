<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 | Broadcast channel authorization. The board channel is tenant-prefixed; a user may subscribe
 | only if they are an active member of THAT tenant — so a second tenant's client is rejected,
 | never receiving another tenant's board events. (Authenticated via the /broadcasting/auth route.)
 */

Broadcast::channel(
    'tenant.{tenantId}.entity.{type}.board',
    fn (User $user, int $tenantId, string $type): bool => $user->belongsToTenant($tenantId),
);

// Tenant config channel: layout.published / field-definition.changed for live schema/layout sync.
Broadcast::channel(
    'tenant.{tenantId}.config',
    fn (User $user, int $tenantId): bool => $user->belongsToTenant($tenantId),
);
