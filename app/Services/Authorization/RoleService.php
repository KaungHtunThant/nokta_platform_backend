<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Enums\RolesAndPermissions\OperationPermission;
use App\Enums\RolesAndPermissions\UiPermission;
use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

/**
 * Tenant-scoped role management (spatie teams). Roles are NOT globally scoped by the package, so
 * every query is explicitly constrained to the active tenant's team id to prevent cross-tenant
 * reads/edits. Permissions are validated against the permanent enum catalog for the role's guard.
 */
final class RoleService
{
    public function __construct(private readonly TenantManager $tenants) {}

    /** @return Collection<int, Role> */
    public function listForTenant(): Collection
    {
        /** @var Collection<int, Role> $roles */
        $roles = Role::query()
            ->where('tenant_id', $this->tenants->id())
            ->with('permissions:id,name,guard_name')
            ->orderBy('name')
            ->get();

        return $roles;
    }

    /**
     * @param  list<string>  $permissions
     */
    public function create(string $name, string $guard, array $permissions): Role
    {
        // team id is set on the request by ResolveTenant, so the new role is auto-scoped to it.
        $role = Role::create(['name' => $name, 'guard_name' => $guard]);
        $role->syncPermissions($this->catalogPermissions($permissions, $guard));

        return $role->load('permissions:id,name,guard_name');
    }

    /**
     * @param  list<string>|null  $permissions
     */
    public function update(Role $role, ?string $name, ?array $permissions): Role
    {
        if ($name !== null) {
            $role->name = $name;
            $role->save();
        }

        if ($permissions !== null) {
            $role->syncPermissions($this->catalogPermissions($permissions, (string) $role->guard_name));
        }

        return $role->load('permissions:id,name,guard_name');
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    /**
     * Keep only permission names that exist in the permanent catalog for the role's guard
     * (roles draw from the fixed catalog; they cannot invent permissions).
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function catalogPermissions(array $names, string $guard): array
    {
        $catalog = $guard === UiPermission::GUARD
            ? array_map(fn (UiPermission $c): string => $c->value, UiPermission::cases())
            : array_map(fn (OperationPermission $c): string => $c->value, OperationPermission::cases());

        return array_values(array_intersect($names, $catalog));
    }
}
