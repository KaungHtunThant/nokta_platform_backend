<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Phase 0 — Tenancy Spine verification (roadmap exit criteria):
 *  - members authenticate and receive a tenant-scoped token; /me resolves the tenant
 *  - non-members are rejected
 *  - row-level isolation: a tenant cannot read another tenant's data
 */
function makeTenant(string $slug): Tenant
{
    return Tenant::create(['slug' => $slug, 'name' => ['en' => ucfirst($slug)]]);
}

function makeUserIn(Tenant $tenant, string $email): User
{
    $user = User::create(['name' => $email, 'email' => $email, 'password' => Hash::make('secret123')]);
    $tenant->users()->attach($user->id, ['status' => 'active']);

    return $user;
}

it('lets a member log in and resolves the tenant on /me', function () {
    $tenant = makeTenant('alpha');
    makeUserIn($tenant, 'a@alpha.test');

    $login = $this->postJson('/api/login', [
        'email' => 'a@alpha.test', 'password' => 'secret123', 'tenant_id' => $tenant->id,
    ]);

    $login->assertOk()->assertJsonPath('tenant_id', $tenant->id);
    $token = $login->json('token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('tenant_id', $tenant->id);
});

it('rejects login for a non-member of the tenant', function () {
    $alpha = makeTenant('alpha');
    $beta = makeTenant('beta');
    makeUserIn($alpha, 'a@alpha.test'); // member of alpha only

    $this->postJson('/api/login', [
        'email' => 'a@alpha.test', 'password' => 'secret123', 'tenant_id' => $beta->id,
    ])->assertForbidden();
});

it('isolates tenant-owned data via the global scope', function () {
    $alpha = makeTenant('alpha');
    $beta = makeTenant('beta');
    $manager = app(TenantManager::class);

    // Seed one setting per tenant (auto-fills tenant_id from context).
    $manager->set($alpha->id);
    TenantSetting::create(['key' => 'theme', 'value' => ['color' => 'red']]);

    $manager->set($beta->id);
    TenantSetting::create(['key' => 'theme', 'value' => ['color' => 'blue']]);

    // Acting as alpha, only alpha's row is visible.
    $manager->set($alpha->id);
    expect(TenantSetting::count())->toBe(1)
        ->and(TenantSetting::first()->value)->toBe(['color' => 'red']);

    // Acting as beta, only beta's row is visible.
    $manager->set($beta->id);
    expect(TenantSetting::count())->toBe(1)
        ->and(TenantSetting::first()->value)->toBe(['color' => 'blue']);
});

it('rejects a token whose tenant membership no longer holds', function () {
    $tenant = makeTenant('alpha');
    $user = makeUserIn($tenant, 'a@alpha.test');
    $token = $user->createToken('t', ["tenant:{$tenant->id}"])->plainTextToken;

    // Revoke membership, then the resolver must reject.
    $tenant->users()->detach($user->id);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/me')
        ->assertForbidden();
});
