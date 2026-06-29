<?php

declare(strict_types=1);

use App\DTOs\RecordInput;
use App\Models\EntityType;
use App\Models\Tenant;
use App\Services\Records\RecordQueryBuilder;
use App\Services\Records\RecordWriteService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Phase 4 — filtering/sorting on custom fields via the EAV projection and JSON fallback.
 * Results must match a direct intent regardless of which storage strategy backs the field.
 */
function bootQueryTenant(): array
{
    $tenant = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    app(TenantManager::class)->set($tenant->id);
    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal']]);
    $deal->fieldDefinitions()->create(['key' => 'title', 'type' => 'text', 'label' => ['en' => 'Title'], 'position' => 0, 'is_sortable' => true]);
    $deal->fieldDefinitions()->create(['key' => 'budget', 'type' => 'money', 'label' => ['en' => 'Budget'], 'position' => 1, 'is_filterable' => true, 'is_sortable' => true]);
    $deal->fieldDefinitions()->create(['key' => 'note', 'type' => 'text', 'label' => ['en' => 'Note'], 'position' => 2]); // JSON-only

    $writer = app(RecordWriteService::class);
    $r1 = $writer->create($deal, new RecordInput(null, null, null, ['title' => 'B', 'budget' => 100, 'note' => 'keep']));
    $r2 = $writer->create($deal, new RecordInput(null, null, null, ['title' => 'A', 'budget' => 300, 'note' => 'drop']));
    $r3 = $writer->create($deal, new RecordInput(null, null, null, ['title' => 'C', 'budget' => 200, 'note' => 'keep']));

    return [$deal, $r1->id, $r2->id, $r3->id];
}

it('filters by an EAV (projected) numeric field', function () {
    [$deal, , $r2, $r3] = bootQueryTenant();

    $ids = app(RecordQueryBuilder::class)
        ->for($deal, [['field' => 'budget', 'op' => 'gte', 'value' => 200]])
        ->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(collect([$r2, $r3])->sort()->values()->all());
});

it('filters by a JSON-only field', function () {
    [$deal, $r1, , $r3] = bootQueryTenant();

    $ids = app(RecordQueryBuilder::class)
        ->for($deal, [['field' => 'note', 'op' => 'eq', 'value' => 'keep']])
        ->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(collect([$r1, $r3])->sort()->values()->all());
});

it('sorts by an EAV numeric field ascending', function () {
    [$deal, $r1, $r2, $r3] = bootQueryTenant();

    $ids = app(RecordQueryBuilder::class)
        ->for($deal, [], ['field' => 'budget', 'dir' => 'asc'])
        ->pluck('id')->all();

    expect($ids)->toBe([$r1, $r3, $r2]); // 100, 200, 300
});

it('sorts by an EAV text field and combines with a locked-column filter', function () {
    [$deal, $r1, $r2, $r3] = bootQueryTenant();

    $ids = app(RecordQueryBuilder::class)
        ->for($deal, [['field' => 'status', 'op' => 'eq', 'value' => null]], ['field' => 'title', 'dir' => 'asc'])
        ->pluck('id')->all();

    expect($ids)->toBe([$r2, $r1, $r3]); // A, B, C
});
