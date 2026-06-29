<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Services\Authorization\RoleService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thin controller for tenant-scoped role management (gated by op:manage.roles). All persistence and
 * scoping live in RoleService; the controller guards that a bound role belongs to the active tenant.
 */
final class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roles,
        private readonly TenantManager $tenants,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection($this->roles->listForTenant());
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roles->create($request->name(), $request->guard(), $request->permissions());

        return RoleResource::make($role)->response()->setStatusCode(201);
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        $this->assertBelongsToTenant($role);

        return RoleResource::make($this->roles->update($role, $request->nameOrNull(), $request->permissionsOrNull()));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->assertBelongsToTenant($role);
        $this->roles->delete($role);

        return response()->json(status: 204);
    }

    /** A role from another tenant must be indistinguishable from one that does not exist. */
    private function assertBelongsToTenant(Role $role): void
    {
        abort_unless((int) $role->getAttribute('tenant_id') === $this->tenants->id(), Response::HTTP_NOT_FOUND);
    }
}
