<?php

declare(strict_types=1);

use App\Events\LayoutPublished;
use App\Models\EntityType;
use App\Models\Layout;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Phase 5 — the layout builder's draft → publish → rollback flow, stale-schema upcasting on read,
 * required-field reachability validation, and access control (op:manage.layouts).
 *
 * @return array{0: Tenant, 1: EntityType, 2: Layout}
 */
function bootBuilderTenant(): array
{
    $tenant = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    app(TenantManager::class)->set($tenant->id);

    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal']]);
    $deal->fieldDefinitions()->create(['key' => 'title', 'type' => 'text', 'label' => ['en' => 'Title'], 'validation' => ['required' => true], 'position' => 0]);
    $deal->fieldDefinitions()->create(['key' => 'note', 'type' => 'textarea', 'label' => ['en' => 'Note'], 'position' => 1]);

    $layout = Layout::create([
        'entity_type_id' => $deal->id, 'surface' => 'form', 'key' => 'deal.form', 'is_active' => true,
        'schema' => ['type' => 'section', 'id' => 'root', 'children' => [
            ['type' => 'field', 'id' => 'f-title', 'binding' => ['field' => 'title']],
            ['type' => 'field', 'id' => 'f-note', 'binding' => ['field' => 'note']],
        ]],
    ]);

    return [$tenant, $deal, $layout];
}

function builderToken(Tenant $tenant, array $ops = ['record.read', 'manage.layouts']): string
{
    $user = User::create(['name' => 'admin', 'email' => 'admin@alpha.test', 'password' => Hash::make('secret123')]);
    $tenant->users()->attach($user->id, ['status' => 'active']);
    grantOps($user, $tenant, $ops);

    return $user->createToken('t', ["tenant:{$tenant->id}"])->plainTextToken;
}

it('saves a draft, publishes it, then rolls back to the prior version', function () {
    [$tenant] = bootBuilderTenant();
    $token = builderToken($tenant);
    $auth = ['Authorization' => "Bearer {$token}"];

    // Reordered schema (note before title) — still contains the required title field.
    $reordered = ['type' => 'section', 'id' => 'root', 'children' => [
        ['type' => 'field', 'id' => 'f-note', 'binding' => ['field' => 'note']],
        ['type' => 'field', 'id' => 'f-title', 'binding' => ['field' => 'title']],
    ]];

    $version = $this->withHeaders($auth)
        ->postJson('/api/layouts/form/deal.form/versions', ['schema' => $reordered, 'note' => 'reorder'])
        ->assertCreated()
        ->json('version');

    expect($version)->toBe(2); // baseline v1 auto-snapshotted, draft is v2

    $this->withHeaders($auth)
        ->postJson('/api/layouts/form/deal.form/publish', ['version' => 2])
        ->assertOk()
        ->assertJsonPath('schema.children.0.binding.field', 'note'); // note now first

    // Roll back to the baseline.
    $this->withHeaders($auth)
        ->postJson('/api/layouts/form/deal.form/rollback', ['version' => 1])
        ->assertOk()
        ->assertJsonPath('schema.children.0.binding.field', 'title'); // original order restored
});

it('blocks publishing a layout that strands a required field', function () {
    [$tenant] = bootBuilderTenant();
    $token = builderToken($tenant);
    $auth = ['Authorization' => "Bearer {$token}"];

    // Schema dropped the required `title` field.
    $broken = ['type' => 'section', 'id' => 'root', 'children' => [
        ['type' => 'field', 'id' => 'f-note', 'binding' => ['field' => 'note']],
    ]];

    $this->withHeaders($auth)->postJson('/api/layouts/form/deal.form/versions', ['schema' => $broken])->assertCreated();

    $this->withHeaders($auth)
        ->postJson('/api/layouts/form/deal.form/publish', ['version' => 2])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['schema']);
});

it('upcasts a stale-schema document on read without breaking', function () {
    $tenant = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    app(TenantManager::class)->set($tenant->id);
    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal']]);

    // v1 doc with a legacy bare-string permission.
    Layout::create([
        'entity_type_id' => $deal->id, 'surface' => 'detail', 'key' => 'deal.detail', 'is_active' => true,
        'schema_version' => 1,
        'schema' => ['type' => 'section', 'id' => 'root', 'permission' => 'ui.section.financials', 'children' => []],
    ]);

    $token = builderToken($tenant);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/layouts/detail/deal.detail')
        ->assertOk()
        ->assertJsonPath('schema_version', 2)
        ->assertJsonPath('schema.permission.ui', 'ui.section.financials'); // normalized
});

it('forbids the builder endpoints without manage.layouts', function () {
    [$tenant] = bootBuilderTenant();
    $token = builderToken($tenant, ['record.read']); // no manage.layouts

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/layouts/form/deal.form/versions', ['schema' => ['type' => 'section', 'id' => 'r']])
        ->assertForbidden();
});

it('broadcasts layout.published on the tenant config channel', function () {
    Event::fake([LayoutPublished::class]);
    [$tenant] = bootBuilderTenant();
    $token = builderToken($tenant);
    $auth = ['Authorization' => "Bearer {$token}"];

    $this->withHeaders($auth)->postJson('/api/layouts/form/deal.form/versions', [
        'schema' => ['type' => 'section', 'id' => 'root', 'children' => [['type' => 'field', 'id' => 'f', 'binding' => ['field' => 'title']]]],
    ])->assertCreated();

    $this->withHeaders($auth)->postJson('/api/layouts/form/deal.form/publish', ['version' => 2])->assertOk();

    Event::assertDispatched(LayoutPublished::class, fn (LayoutPublished $e): bool => $e->layout->key === 'deal.form');
});
