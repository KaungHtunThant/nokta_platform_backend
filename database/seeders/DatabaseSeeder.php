<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RolesAndPermissions\OperationPermission;
use App\Enums\RolesAndPermissions\UiPermission;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Permanent permission catalog (two guards) — seeded once, before any roles reference it.
        $this->call(PermissionsSeeder::class);

        // Two demo tenants (clinic brands), each with one member + a tenant-scoped setting.
        $manager = app(TenantManager::class);

        foreach (['nokta' => 'admin@nokta.test', 'mediceva' => 'admin@mediceva.test'] as $slug => $email) {
            $tenant = Tenant::firstOrCreate(
                ['slug' => $slug],
                ['name' => ['en' => ucfirst($slug)], 'status' => 'active']
            );

            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => ucfirst($slug).' Admin', 'password' => Hash::make('password')]
            );

            $tenant->users()->syncWithoutDetaching([$user->id => ['status' => 'active']]);

            $manager->set($tenant->id);
            TenantSetting::updateOrCreate(['key' => 'brand.color'], ['value' => ['hex' => $slug === 'nokta' ? '#dd3636' : '#2e74b5']]);
            $this->seedAdmin($tenant->id, $user);
            $manager->forget();
        }

        // Phase 1: demo 'deal' entity type + fields + layouts.
        $this->call(DemoEntitySeeder::class);
    }

    /**
     * Give the tenant's demo user a full-access Admin role on each guard (op + ui), drawing from the
     * permanent catalog. Tenant-scoped via spatie teams. Lets the seeded login exercise everything.
     */
    private function seedAdmin(int $tenantId, User $user): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

        $opRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => OperationPermission::GUARD]);
        $opRole->syncPermissions(array_map(fn (OperationPermission $c): string => $c->value, OperationPermission::cases()));

        $uiRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => UiPermission::GUARD]);
        $uiRole->syncPermissions(array_map(fn (UiPermission $c): string => $c->value, UiPermission::cases()));

        $user->syncRoles([$opRole, $uiRole]);
    }
}
