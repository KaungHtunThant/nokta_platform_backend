<?php

declare(strict_types=1);

use App\Enums\RolesAndPermissions\OperationPermission;
use App\Enums\RolesAndPermissions\UiPermission;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Phase 3 — the permission catalog is enum-backed and seeded once under two guards.
 * Operation perms (guard "op") are the security source of truth; UI perms (guard "ui")
 * drive rendering only. Roles draw from this fixed catalog; the catalog itself is permanent.
 */
it('seeds every operation permission under the op guard', function () {
    $this->seed(PermissionsSeeder::class);

    foreach (OperationPermission::cases() as $case) {
        expect(Permission::where('name', $case->value)->where('guard_name', 'op')->exists())
            ->toBeTrue("missing op permission {$case->value}");
    }

    expect(Permission::where('guard_name', 'op')->count())
        ->toBe(count(OperationPermission::cases()));
});

it('seeds every ui permission under the ui guard', function () {
    $this->seed(PermissionsSeeder::class);

    foreach (UiPermission::cases() as $case) {
        expect(Permission::where('name', $case->value)->where('guard_name', 'ui')->exists())
            ->toBeTrue("missing ui permission {$case->value}");
    }

    expect(Permission::where('guard_name', 'ui')->count())
        ->toBe(count(UiPermission::cases()));
});

it('is idempotent — re-seeding creates no duplicates', function () {
    $this->seed(PermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $expected = count(OperationPermission::cases()) + count(UiPermission::cases());
    expect(Permission::count())->toBe($expected);
});

it('registers the op and ui guards in auth config', function () {
    expect(config('auth.guards.op'))->not->toBeNull()
        ->and(config('auth.guards.ui'))->not->toBeNull()
        ->and(config('auth.guards.op.provider'))->toBe('users')
        ->and(config('auth.guards.ui.provider'))->toBe('users');
});
