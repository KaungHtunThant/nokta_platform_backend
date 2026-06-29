<?php

declare(strict_types=1);

use App\Models\EntityType;
use App\Models\RoleFieldAccess;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantManager;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function bootAbilitiesUser(Tenant $tenant, array $ops, array $uis = []): array
{
    $user = User::create(['name' => 'u', 'email' => 'u@alpha.test', 'password' => Hash::make('secret123')]);
    $tenant->users()->attach($user->id, ['status' => 'active']);
    $opRole = grantOps($user, $tenant, $ops);

    if ($uis !== []) {
        setPermissionsTeamId($tenant->id);
        $uiRole = Role::firstOrCreate(['name' => 'role-ui', 'guard_name' => 'ui']);
        $uiRole->syncPermissions($uis);
        $user->assignRole($uiRole);
    }

    $token = $user->createToken('t', ["tenant:{$tenant->id}"])->plainTextToken;

    return [$user, $opRole, $token];
}

it('returns resolved op and ui permissions plus the field matrix', function () {
    $tenant = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    app(TenantManager::class)->set($tenant->id);
    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal']]);
    $secret = $deal->fieldDefinitions()->create(['key' => 'secret', 'type' => 'text', 'label' => ['en' => 'Secret'], 'position' => 0]);

    [, $opRole, $token] = bootAbilitiesUser($tenant, ['record.read', 'record.update'], ['ui.nav.boards']);

    setPermissionsTeamId($tenant->id);
    RoleFieldAccess::create(['role_id' => $opRole->id, 'field_definition_id' => $secret->id, 'can_read' => true, 'can_update' => false, 'ui_visible' => false]);

    $res = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/me/abilities')
        ->assertOk();

    expect($res->json('op'))->toContain('record.read', 'record.update')
        ->and($res->json('ui'))->toContain('ui.nav.boards')
        ->and($res->json('fields.secret'))->toBe(['can_read' => true, 'can_update' => false, 'ui_visible' => false]);
});

it('lets a manage.roles user create, update and delete a tenant role', function () {
    $tenant = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    app(TenantManager::class)->set($tenant->id);
    [, , $token] = bootAbilitiesUser($tenant, ['manage.roles']);

    $created = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/roles', ['name' => 'Sales', 'guard' => 'op', 'permissions' => ['record.read', 'not.a.real.perm']])
        ->assertCreated()
        ->assertJsonPath('name', 'Sales')
        ->assertJsonPath('permissions', ['record.read']) // bogus perm filtered out by catalog
        ->json('id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/roles')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Sales']);

    $updated = $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/roles/{$created}", ['name' => 'Sales Rep', 'permissions' => ['record.read', 'record.create']])
        ->assertOk()
        ->assertJsonPath('name', 'Sales Rep');

    expect($updated->json('permissions'))->toEqualCanonicalizing(['record.read', 'record.create']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/roles/{$created}")
        ->assertNoContent();
});

it('forbids role management without manage.roles', function () {
    $tenant = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    app(TenantManager::class)->set($tenant->id);
    [, , $token] = bootAbilitiesUser($tenant, ['record.read']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/roles', ['name' => 'X', 'guard' => 'op'])
        ->assertForbidden();
});

it('cannot modify a role belonging to another tenant (404)', function () {
    $alpha = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    $beta = Tenant::create(['slug' => 'beta', 'name' => ['en' => 'Beta']]);

    // A role owned by beta.
    app(TenantManager::class)->set($beta->id);
    (new PermissionsSeeder)->run();
    setPermissionsTeamId($beta->id);
    $betaRole = Role::create(['name' => 'BetaRole', 'guard_name' => 'op']);

    // An alpha admin tries to edit it.
    app(TenantManager::class)->set($alpha->id);
    [, , $token] = bootAbilitiesUser($alpha, ['manage.roles']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/roles/{$betaRole->id}", ['name' => 'hijacked'])
        ->assertNotFound();

    // Raw query (not team-scoped) confirms beta's role is untouched.
    expect(Role::query()->where('id', $betaRole->id)->value('name'))->toBe('BetaRole');
});
