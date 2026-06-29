<?php

declare(strict_types=1);

use App\Models\EntityType;
use App\Models\FieldDefinition;
use App\Models\Record;
use App\Models\RoleFieldAccess;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Phase 3 exit criteria (ARCHITECTURE §6): operation-side field enforcement via FieldGate.
 *  - a field a role may not UPDATE is stripped on write even if force-sent;
 *  - a field a role may not READ never appears in the response;
 *  - ui_visible only HIDES — it never blocks read/write on the operation side.
 *
 * @return array{0: Tenant, 1: EntityType, 2: FieldDefinition, 3: FieldDefinition}
 */
function bootFieldGateTenant(): array
{
    $tenant = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    app(TenantManager::class)->set($tenant->id);
    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal']]);
    $title = $deal->fieldDefinitions()->create(['key' => 'title', 'type' => 'text', 'label' => ['en' => 'Title'], 'position' => 0]);
    $secret = $deal->fieldDefinitions()->create(['key' => 'secret', 'type' => 'text', 'label' => ['en' => 'Secret'], 'position' => 1]);

    return [$tenant, $deal, $title, $secret];
}

function fieldGateActor(Tenant $tenant, array $ops): array
{
    $user = User::create(['name' => 'u', 'email' => 'u@alpha.test', 'password' => Hash::make('secret123')]);
    $tenant->users()->attach($user->id, ['status' => 'active']);
    $role = grantOps($user, $tenant, $ops);
    $token = $user->createToken('t', ["tenant:{$tenant->id}"])->plainTextToken;

    return [$user, $role, $token];
}

it('strips a non-updatable field on write even when the client force-sends it', function () {
    [$tenant, $deal, , $secret] = bootFieldGateTenant();
    [, $role, $token] = fieldGateActor($tenant, ['record.read', 'record.update']);

    setPermissionsTeamId($tenant->id);
    RoleFieldAccess::create([
        'role_id' => $role->id, 'field_definition_id' => $secret->id,
        'can_read' => true, 'can_update' => false, 'ui_visible' => true,
    ]);

    $record = Record::create(['entity_type_id' => $deal->id, 'data' => ['title' => 'A', 'secret' => 'original']]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/records/{$record->id}", ['data' => ['title' => 'B', 'secret' => 'HACKED']])
        ->assertOk();

    $fresh = $record->fresh();
    expect($fresh->data['title'])->toBe('B')          // permitted edit applied
        ->and($fresh->data['secret'])->toBe('original'); // denied write ignored, existing value preserved
});

it('omits a non-readable field from the response', function () {
    [$tenant, $deal, , $secret] = bootFieldGateTenant();
    [, $role, $token] = fieldGateActor($tenant, ['record.read']);

    setPermissionsTeamId($tenant->id);
    RoleFieldAccess::create([
        'role_id' => $role->id, 'field_definition_id' => $secret->id,
        'can_read' => false, 'can_update' => false, 'ui_visible' => true,
    ]);

    $record = Record::create(['entity_type_id' => $deal->id, 'data' => ['title' => 'A', 'secret' => 'PHI']]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/records/{$record->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'A')
        ->assertJsonMissingPath('data.secret');
});

it('treats ui_visible=false as hide-only — read and write still work on the operation side', function () {
    [$tenant, $deal, , $secret] = bootFieldGateTenant();
    [, $role, $token] = fieldGateActor($tenant, ['record.read', 'record.update']);

    setPermissionsTeamId($tenant->id);
    RoleFieldAccess::create([
        'role_id' => $role->id, 'field_definition_id' => $secret->id,
        'can_read' => true, 'can_update' => true, 'ui_visible' => false, // hidden in UI only
    ]);

    $record = Record::create(['entity_type_id' => $deal->id, 'data' => ['title' => 'A', 'secret' => 'v1']]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/records/{$record->id}", ['data' => ['secret' => 'v2']])
        ->assertOk()
        ->assertJsonPath('data.secret', 'v2'); // readable + writable despite ui_visible=false

    expect($record->fresh()->data['secret'])->toBe('v2');
});

it('does not strip anything when no field-access rows exist (open by default)', function () {
    [$tenant, $deal] = bootFieldGateTenant();
    [, , $token] = fieldGateActor($tenant, ['record.read', 'record.update']);

    $record = Record::create(['entity_type_id' => $deal->id, 'data' => ['title' => 'A', 'secret' => 's']]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/records/{$record->id}")
        ->assertOk()
        ->assertJsonPath('data.secret', 's');
});
