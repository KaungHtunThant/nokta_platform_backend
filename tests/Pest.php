<?php

declare(strict_types=1);
use App\Enums\RolesAndPermissions\OperationPermission;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
 | Pest bootstrap. Feature/Unit tests extend Laravel's TestCase.
 | Architecture tests (tests/Arch) enforce the MVC + SOLID layering — see ArchTest.php.
 */

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Grant a user a tenant-scoped role on the "op" guard carrying the given operation permissions
 * (defaults to the full catalog). Seeds the permanent catalog first. Used by feature tests now that
 * record/board routes are permission-gated (Phase 3).
 *
 * @param  list<string>|null  $ops  permission names; null = every OperationPermission case
 */
function grantOps(User $user, Tenant $tenant, ?array $ops = null): Role
{
    (new PermissionsSeeder)->run();

    setPermissionsTeamId($tenant->id); // spatie teams: scope the role + assignment to this tenant

    $ops ??= array_map(fn (OperationPermission $c): string => $c->value, OperationPermission::cases());

    $role = Role::firstOrCreate(['name' => 'role-op-'.substr(md5(implode(',', $ops)), 0, 12), 'guard_name' => OperationPermission::GUARD]);
    $role->syncPermissions($ops);
    $user->assignRole($role);

    return $role;
}
