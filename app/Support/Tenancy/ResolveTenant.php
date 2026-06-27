<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves tenant context for an authenticated request from the access token's
 * "tenant:{id}" ability, verifies the user is an active member, then sets the
 * TenantManager and the spatie permissions team id. Rejects (403) otherwise.
 *
 * Runs after auth:sanctum.
 */
final class ResolveTenant
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        }

        $tenantId = $this->tenantIdFromToken($user);

        if ($tenantId === null) {
            abort(Response::HTTP_FORBIDDEN, 'No tenant context on this token.');
        }

        if (! $user->belongsToTenant($tenantId)) {
            abort(Response::HTTP_FORBIDDEN, 'You are not a member of this tenant.');
        }

        $this->tenants->set($tenantId);
        setPermissionsTeamId($tenantId); // spatie teams

        return $next($request);
    }

    private function tenantIdFromToken(User $user): ?int
    {
        $token = $user->currentAccessToken();

        if ($token === null) {
            return null;
        }

        /** @var list<string> $abilities */
        $abilities = $token->abilities ?? [];

        foreach ($abilities as $ability) {
            if (str_starts_with($ability, 'tenant:')) {
                return (int) substr($ability, strlen('tenant:'));
            }
        }

        return null;
    }
}
