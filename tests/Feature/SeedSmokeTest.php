<?php

declare(strict_types=1);

use App\Models\Tenant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Phase 3 end-to-end: the full demo seed yields a tenant admin whose login resolves real op/ui
 * abilities and a permission-gated nav layout — proving the catalog, roles, and nav wiring connect.
 */
it('seeds a tenant admin that logs in with resolved abilities and a nav layout', function () {
    $this->seed(DatabaseSeeder::class);

    $nokta = Tenant::query()->where('slug', 'nokta')->firstOrFail();

    $token = $this->postJson('/api/login', [
        'email' => 'admin@nokta.test', 'password' => 'password', 'tenant_id' => $nokta->id,
    ])->assertOk()->json('token');

    // Abilities resolve the seeded Admin role on both guards + reflect enforcement.
    $abilities = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/me/abilities')
        ->assertOk();

    expect($abilities->json('op'))->toContain('record.read', 'stage.move', 'manage.roles')
        ->and($abilities->json('ui'))->toContain('ui.nav.boards', 'ui.nav.list');

    // The nav layout is served and carries permission-gated nav items.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/layouts/nav/main')
        ->assertOk()
        ->assertJsonPath('surface', 'nav')
        ->assertJsonPath('schema.children.0.type', 'nav-item')
        ->assertJsonPath('schema.children.0.permission.ui', 'ui.nav.boards');
});
