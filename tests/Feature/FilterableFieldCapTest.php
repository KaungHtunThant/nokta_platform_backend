<?php

declare(strict_types=1);

use App\Models\EntityType;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function bootCapTenant(int $limit): string
{
    $tenant = Tenant::create(['slug' => 'cap', 'name' => ['en' => 'Cap']]);
    app(TenantManager::class)->set($tenant->id);

    EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal']]);
    TenantSetting::create(['tenant_id' => $tenant->id, 'key' => 'limits', 'value' => ['filterable_fields' => $limit]]);

    $user = User::create(['name' => 'u', 'email' => 'u@cap.test', 'password' => Hash::make('secret123')]);
    $tenant->users()->attach($user->id, ['status' => 'active']);
    grantOps($user, $tenant);

    return $user->createToken('t', ["tenant:{$tenant->id}"])->plainTextToken;
}

function addField(string $token, array $payload)
{
    return test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/entity-types/deal/fields', array_merge(['label' => ['en' => 'F'], 'type' => 'text'], $payload));
}

it('enforces the per-tenant filterable-field cap', function () {
    $token = bootCapTenant(1);

    addField($token, ['key' => 'a', 'is_filterable' => true])->assertCreated();   // used 0 -> ok
    addField($token, ['key' => 'b', 'is_filterable' => true])->assertStatus(422);  // at cap

    // Non-filterable fields are unaffected by the cap.
    addField($token, ['key' => 'c', 'is_filterable' => false])->assertCreated();
});

it('exposes the remaining filterable budget on the schema endpoint', function () {
    $token = bootCapTenant(3);
    addField($token, ['key' => 'a', 'is_filterable' => true])->assertCreated();

    test()->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/entity-types/deal/schema')
        ->assertOk()
        ->assertJsonPath('limits.filterable_remaining', 2);
});
