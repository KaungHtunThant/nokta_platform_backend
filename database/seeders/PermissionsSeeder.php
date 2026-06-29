<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RolesAndPermissions\OperationPermission;
use App\Enums\RolesAndPermissions\UiPermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the PERMANENT permission catalog (decision #5 / ARCHITECTURE §6) under two guards.
 * The enums are the source of truth: every case becomes one spatie Permission row, seeded once.
 * Permissions are global (not team-scoped); roles — which draw from this catalog — are per-tenant.
 * Idempotent: safe to re-run (findOrCreate).
 */
final class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (OperationPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, OperationPermission::GUARD);
        }

        foreach (UiPermission::cases() as $permission) {
            Permission::findOrCreate($permission->value, UiPermission::GUARD);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
