<?php

declare(strict_types=1);

use App\Enums\RolesAndPermissions\OperationPermission;
use App\Models\EntityType;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function bootAuditTenant(?array $ops = null): array
{
    // owen-it skips auditing when running in console; test HTTP requests run in the console process,
    // so enable it to exercise the trail (production HTTP is unaffected — it isn't console).
    config(['audit.console' => true]);

    $tenant = Tenant::create(['slug' => 'audit', 'name' => ['en' => 'Audit']]);
    app(TenantManager::class)->set($tenant->id);

    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal'], 'supports_pipeline' => true, 'config' => ['title_field' => 'title']]);
    $deal->fieldDefinitions()->create(['key' => 'title', 'type' => 'text', 'label' => ['en' => 'Title'], 'validation' => ['required' => true], 'storage_strategy' => 'json', 'position' => 0]);

    $user = User::create(['name' => 'u', 'email' => 'u@audit.test', 'password' => Hash::make('secret123')]);
    $tenant->users()->attach($user->id, ['status' => 'active']);
    grantOps($user, $tenant, $ops);

    return [$tenant, $deal, $user->createToken('t', ["tenant:{$tenant->id}"])->plainTextToken];
}

it('records an audit trail with old/new values on update', function () {
    [, , $token] = bootAuditTenant();

    $id = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/entity-types/deal/records', ['data' => ['title' => 'Original']])
        ->assertCreated()->json('id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/records/{$id}", ['data' => ['title' => 'Updated']])
        ->assertOk();

    $audits = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/records/{$id}/audits")
        ->assertOk()
        ->json();

    $events = array_column($audits, 'event');
    expect($events)->toContain('created')->toContain('updated');

    // A json-cast column is captured by owen-it as its encoded form; normalise before asserting.
    $dataValue = function (array $values): array {
        $data = $values['data'] ?? [];

        return is_string($data) ? (json_decode($data, true) ?? []) : $data;
    };

    $updated = collect($audits)->firstWhere('event', 'updated');
    expect($dataValue($updated['new_values'])['title'] ?? null)->toBe('Updated')
        ->and($dataValue($updated['old_values'])['title'] ?? null)->toBe('Original');
});

it('gates the audit trail behind audit.view', function () {
    $ops = array_values(array_filter(
        array_map(fn (OperationPermission $c): string => $c->value, OperationPermission::cases()),
        fn (string $v): bool => $v !== 'audit.view',
    ));
    [, , $token] = bootAuditTenant($ops);

    $id = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/entity-types/deal/records', ['data' => ['title' => 'X']])
        ->assertCreated()->json('id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/records/{$id}/audits")
        ->assertForbidden();
});
