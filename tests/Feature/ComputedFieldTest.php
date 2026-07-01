<?php

declare(strict_types=1);

use App\DTOs\RecordInput;
use App\Models\EntityType;
use App\Models\RecordValue;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Records\RecordWriteService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Phase 7b — `computed` fields are derived server-side on write and persisted into records.data so they
 * project into the EAV table (filter/sort) like native fields, and are read-only end to end.
 *
 * @return array{0: Tenant, 1: EntityType}
 */
function bootComputedTenant(): array
{
    $tenant = Tenant::create(['slug' => 'comp', 'name' => ['en' => 'Comp']]);
    app(TenantManager::class)->set($tenant->id);
    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal']]);
    $deal->fieldDefinitions()->create(['key' => 'budget', 'type' => 'money', 'label' => ['en' => 'Budget'], 'position' => 0, 'is_filterable' => true]);
    $deal->fieldDefinitions()->create([
        'key' => 'budget_band', 'type' => 'computed', 'label' => ['en' => 'Band'], 'position' => 1,
        'ui' => ['expression' => 'budget >= 1000 ? "high" : "low"', 'result_type' => 'text'],
        'is_filterable' => true, 'is_sortable' => true,
    ]);

    return [$tenant, $deal];
}

it('computes a field value server-side on create', function () {
    [, $deal] = bootComputedTenant();

    $record = app(RecordWriteService::class)->create($deal, new RecordInput(null, null, null, ['budget' => 1500]));

    expect($record->data['budget_band'])->toBe('high');
});

it('recomputes when a dependency changes on update', function () {
    [, $deal] = bootComputedTenant();
    $writer = app(RecordWriteService::class);

    $record = $writer->create($deal, new RecordInput(null, null, null, ['budget' => 1500]));
    expect($record->data['budget_band'])->toBe('high');

    $record = $writer->update($deal, $record, new RecordInput(null, null, null, ['budget' => 200]));
    expect($record->data['budget_band'])->toBe('low');
});

it('ignores a client-supplied value for a computed field (read-only)', function () {
    [, $deal] = bootComputedTenant();

    // A forced payload tries to set budget_band directly; it is always overwritten by the derived value.
    $record = app(RecordWriteService::class)->create($deal, new RecordInput(null, null, null, [
        'budget' => 1500, 'budget_band' => 'HACKED',
    ]));

    expect($record->data['budget_band'])->toBe('high');
});

it('projects the computed value into the EAV table by result_type (filterable/sortable)', function () {
    [, $deal] = bootComputedTenant();

    $record = app(RecordWriteService::class)->create($deal, new RecordInput(null, null, null, ['budget' => 1500]));

    $value = RecordValue::query()
        ->whereHas('fieldDefinition', fn ($q) => $q->where('key', 'budget_band'))
        ->where('record_id', $record->id)
        ->first();

    expect($value)->not->toBeNull()
        ->and($value->value_string)->toBe('high');  // result_type text → value_string slot
});

it('never fails a write when the expression is bad (value becomes null)', function () {
    $tenant = Tenant::create(['slug' => 'bad', 'name' => ['en' => 'Bad']]);
    app(TenantManager::class)->set($tenant->id);
    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal']]);
    $deal->fieldDefinitions()->create([
        'key' => 'broken', 'type' => 'computed', 'label' => ['en' => 'Broken'], 'position' => 0,
        'ui' => ['expression' => '1 +', 'result_type' => 'text'],
    ]);

    $record = app(RecordWriteService::class)->create($deal, new RecordInput(null, null, null, []));

    expect($record->data)->toHaveKey('broken')
        ->and($record->data['broken'])->toBeNull();
});

it('rejects a computed field with an unparseable expression at definition time (422)', function () {
    $tenant = Tenant::create(['slug' => 'def', 'name' => ['en' => 'Def']]);
    app(TenantManager::class)->set($tenant->id);
    EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal']]);

    $user = User::create(['name' => 'u', 'email' => 'u@def.test', 'password' => Hash::make('secret123')]);
    $tenant->users()->attach($user->id, ['status' => 'active']);
    grantOps($user, $tenant);
    $token = $user->createToken('t', ["tenant:{$tenant->id}"])->plainTextToken;

    // Unparseable expression → 422.
    test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/entity-types/deal/fields', [
            'key' => 'bad', 'type' => 'computed', 'label' => ['en' => 'Bad'],
            'ui' => ['expression' => '1 +', 'result_type' => 'text'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ui.expression');

    // A valid expression is accepted.
    test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/entity-types/deal/fields', [
            'key' => 'ok', 'type' => 'computed', 'label' => ['en' => 'Ok'],
            'ui' => ['expression' => 'budget >= 1000 ? "high" : "low"', 'result_type' => 'text'],
        ])
        ->assertCreated();
});
