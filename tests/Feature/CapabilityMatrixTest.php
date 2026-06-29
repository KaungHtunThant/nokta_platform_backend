<?php

declare(strict_types=1);

use App\Models\EntityType;
use App\Models\Pipeline;
use App\Models\RoleFieldAccess;
use App\Models\RoleStageAccess;
use App\Models\Tenant;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Phase 3 — the per-stage and per-field capability matrices (ARCHITECTURE §6).
 * Both are tenant-scoped (auto-filled tenant_id, global-scoped reads) and cast booleans.
 */
it('stores a tenant-scoped role-stage access row with boolean casts', function () {
    $tenant = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    app(TenantManager::class)->set($tenant->id);
    setPermissionsTeamId($tenant->id);

    $role = Role::create(['name' => 'Sales', 'guard_name' => 'op']);
    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal'], 'supports_pipeline' => true]);
    $pipeline = Pipeline::create(['entity_type_id' => $deal->id, 'key' => 'sales', 'label' => ['en' => 'Sales'], 'position' => 0]);
    $stage = $pipeline->stages()->create(['key' => 'new', 'label' => ['en' => 'New'], 'position' => 0]);

    $access = RoleStageAccess::create([
        'role_id' => $role->id,
        'stage_id' => $stage->id,
        'can_move_from' => true,
        'can_move_to' => 0,
        'can_view' => true,
    ]);

    expect($access->tenant_id)->toBe($tenant->id)
        ->and($access->can_move_from)->toBeTrue()
        ->and($access->can_move_to)->toBeFalse()
        ->and($access->stage->is($stage))->toBeTrue()
        ->and($access->role->is($role))->toBeTrue();
});

it('stores a tenant-scoped role-field access row and isolates it from another tenant', function () {
    $alpha = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    $beta = Tenant::create(['slug' => 'beta', 'name' => ['en' => 'Beta']]);
    $manager = app(TenantManager::class);

    $manager->set($alpha->id);
    setPermissionsTeamId($alpha->id);
    $role = Role::create(['name' => 'Viewer', 'guard_name' => 'op']);
    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal']]);
    $field = $deal->fieldDefinitions()->create(['key' => 'budget', 'type' => 'money', 'label' => ['en' => 'Budget'], 'position' => 0]);
    RoleFieldAccess::create([
        'role_id' => $role->id,
        'field_definition_id' => $field->id,
        'can_read' => true,
        'can_update' => false,
        'ui_visible' => false,
    ]);

    expect(RoleFieldAccess::count())->toBe(1);

    // From beta's context, alpha's row is invisible (global scope).
    $manager->set($beta->id);
    expect(RoleFieldAccess::count())->toBe(0);
});
