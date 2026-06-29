<?php

declare(strict_types=1);

use App\DTOs\RecordInput;
use App\Models\EntityType;
use App\Models\RecordValue;
use App\Models\Tenant;
use App\Services\Records\RecordWriteService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Phase 4 — the write funnel keeps the EAV projection (record_values) in sync for filterable/sortable
 * fields, typed by field type. JSON stays the source of truth.
 *
 * @return array{0: Tenant, 1: EntityType}
 */
function bootProjectionTenant(): array
{
    $tenant = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    app(TenantManager::class)->set($tenant->id);
    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal']]);
    $deal->fieldDefinitions()->create(['key' => 'title', 'type' => 'text', 'label' => ['en' => 'Title'], 'position' => 0, 'is_sortable' => true]);
    $deal->fieldDefinitions()->create(['key' => 'budget', 'type' => 'money', 'label' => ['en' => 'Budget'], 'position' => 1, 'is_filterable' => true]);
    $deal->fieldDefinitions()->create(['key' => 'vip', 'type' => 'bool', 'label' => ['en' => 'VIP'], 'position' => 2, 'is_filterable' => true]);
    $deal->fieldDefinitions()->create(['key' => 'note', 'type' => 'textarea', 'label' => ['en' => 'Note'], 'position' => 3]); // not projected

    return [$tenant, $deal];
}

it('projects filterable/sortable fields into typed record_values on create', function () {
    [, $deal] = bootProjectionTenant();

    $record = app(RecordWriteService::class)->create($deal, new RecordInput(null, null, null, [
        'title' => 'Hip', 'budget' => 1500, 'vip' => true, 'note' => 'ignored',
    ]));

    $byField = RecordValue::query()->get()->keyBy(fn (RecordValue $v) => $v->fieldDefinition->key);

    expect($byField)->toHaveCount(3) // title, budget, vip — NOT note
        ->and($byField['title']->value_string)->toBe('Hip')
        ->and($byField['budget']->value_number)->toBe(1500.0)
        ->and($byField['vip']->value_bool)->toBeTrue()
        ->and($byField->has('note'))->toBeFalse();
});

it('updates the projection idempotently on update (one row per field)', function () {
    [, $deal] = bootProjectionTenant();
    $writer = app(RecordWriteService::class);

    $record = $writer->create($deal, new RecordInput(null, null, null, ['title' => 'A', 'budget' => 100]));
    $writer->update($deal, $record, new RecordInput(null, null, null, ['title' => 'A', 'budget' => 250]));

    $budget = RecordValue::query()->whereHas('fieldDefinition', fn ($q) => $q->where('key', 'budget'))->get();

    expect($budget)->toHaveCount(1)
        ->and($budget->first()->value_number)->toBe(250.0);
});

it('clears the slot when a projected value becomes empty', function () {
    [, $deal] = bootProjectionTenant();
    $writer = app(RecordWriteService::class);

    $record = $writer->create($deal, new RecordInput(null, null, null, ['title' => 'A', 'budget' => 100]));
    $writer->update($deal, $record, new RecordInput(null, null, null, ['title' => 'A', 'budget' => '']));

    $budget = RecordValue::query()->whereHas('fieldDefinition', fn ($q) => $q->where('key', 'budget'))->first();
    expect($budget->value_number)->toBeNull();
});
