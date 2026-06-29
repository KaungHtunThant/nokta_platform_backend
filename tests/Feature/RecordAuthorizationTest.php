<?php

declare(strict_types=1);

use App\Models\EntityType;
use App\Models\Record;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Phase 3 — operation-permission enforcement on record routes. The "op" guard is the security
 * source of truth: coarse middleware gates collection routes, RecordPolicy gates bound routes.
 */
function bootAuthzTenant(): array
{
    $tenant = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    app(TenantManager::class)->set($tenant->id);
    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal']]);
    $deal->fieldDefinitions()->create(['key' => 'title', 'type' => 'text', 'label' => ['en' => 'Title'], 'validation' => ['required' => true], 'position' => 0]);

    return [$tenant, $deal];
}

/** @param list<string> $ops */
function tokenWithOps(Tenant $tenant, array $ops, string $email = 'u@alpha.test'): string
{
    $user = User::create(['name' => $email, 'email' => $email, 'password' => Hash::make('secret123')]);
    $tenant->users()->attach($user->id, ['status' => 'active']);
    grantOps($user, $tenant, $ops);

    return $user->createToken('t', ["tenant:{$tenant->id}"])->plainTextToken;
}

it('allows reading records with record.read but forbids creating without record.create', function () {
    [$tenant] = bootAuthzTenant();
    $token = tokenWithOps($tenant, ['record.read']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/entity-types/deal/records')
        ->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/entity-types/deal/records', ['data' => ['title' => 'X']])
        ->assertForbidden();
});

it('forbids updating a record without record.update (RecordPolicy)', function () {
    [$tenant, $deal] = bootAuthzTenant();
    $record = Record::create(['entity_type_id' => $deal->id, 'data' => ['title' => 'X']]);
    $token = tokenWithOps($tenant, ['record.read']); // no update

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/records/{$record->id}", ['data' => ['title' => 'Y']])
        ->assertForbidden();

    expect($record->fresh()->data['title'])->toBe('X');
});

it('allows updating a record with record.update', function () {
    [$tenant, $deal] = bootAuthzTenant();
    $record = Record::create(['entity_type_id' => $deal->id, 'data' => ['title' => 'X']]);
    $token = tokenWithOps($tenant, ['record.read', 'record.update']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/records/{$record->id}", ['data' => ['title' => 'Y']])
        ->assertOk();

    expect($record->fresh()->data['title'])->toBe('Y');
});

it('forbids deleting a record without record.delete', function () {
    [$tenant, $deal] = bootAuthzTenant();
    $record = Record::create(['entity_type_id' => $deal->id, 'data' => ['title' => 'X']]);
    $token = tokenWithOps($tenant, ['record.read', 'record.update']); // no delete

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/records/{$record->id}")
        ->assertForbidden();
});

it('rejects a record route entirely for a user with no operation permissions', function () {
    [$tenant] = bootAuthzTenant();
    $token = tokenWithOps($tenant, []); // member, but zero op permissions

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/entity-types/deal/records')
        ->assertForbidden();
});
