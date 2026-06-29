<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\RolesAndPermissions\OperationPermission;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coarse operation-permission gate for routes with no bound model (index, create, board, config).
 * Checks the "op" guard EXPLICITLY — the user belongs to multiple guards (web/op/ui/sanctum on the
 * same provider), so the default-guard derivation that spatie's own middleware relies on cannot be
 * trusted. Object-level checks (ownership) go through RecordPolicy via the `can:` middleware instead.
 *
 * Usage: ->middleware('op:record.read')
 */
final class EnsureOperationPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || ! $user->hasPermissionTo($permission, OperationPermission::GUARD)) {
            abort(Response::HTTP_FORBIDDEN, 'Missing operation permission: '.$permission);
        }

        return $next($request);
    }
}
