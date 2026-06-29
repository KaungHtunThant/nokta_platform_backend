<?php

declare(strict_types=1);

use App\DTOs\RecordInput;
use App\Jobs\RebuildProjection;
use App\Models\EntityType;
use App\Models\RecordValue;
use App\Models\Tenant;
use App\Services\Records\RecordWriteService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Phase 4 exit criterion: drop record_values and rebuild from JSON → parity. Proves JSON is the
 * source of truth and the projection is disposable.
 */
it('rebuilds the EAV projection from JSON with parity after a wipe', function () {
    $tenant = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    app(TenantManager::class)->set($tenant->id);
    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal']]);
    $deal->fieldDefinitions()->create(['key' => 'budget', 'type' => 'money', 'label' => ['en' => 'Budget'], 'position' => 0, 'is_filterable' => true]);

    $writer = app(RecordWriteService::class);
    $writer->create($deal, new RecordInput(null, null, null, ['budget' => 100]));
    $writer->create($deal, new RecordInput(null, null, null, ['budget' => 250]));

    $before = RecordValue::query()->orderBy('record_id')->pluck('value_number')->all();
    expect($before)->toBe([100.0, 250.0]);

    // Wipe the projection — JSON is untouched.
    RecordValue::query()->delete();
    expect(RecordValue::query()->count())->toBe(0);

    RebuildProjection::dispatchSync($tenant->id, $deal->id);

    $after = RecordValue::query()->orderBy('record_id')->pluck('value_number')->all();
    expect($after)->toBe($before); // parity restored from JSON alone
});

it('drops projection rows for a field that is no longer projected on rebuild', function () {
    $tenant = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    app(TenantManager::class)->set($tenant->id);
    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal']]);
    $budget = $deal->fieldDefinitions()->create(['key' => 'budget', 'type' => 'money', 'label' => ['en' => 'Budget'], 'position' => 0, 'is_filterable' => true]);

    app(RecordWriteService::class)->create($deal, new RecordInput(null, null, null, ['budget' => 100]));
    expect(RecordValue::query()->count())->toBe(1);

    // Field is reconfigured to no longer be filterable/sortable.
    $budget->update(['is_filterable' => false, 'is_sortable' => false]);

    RebuildProjection::dispatchSync($tenant->id, $deal->id);

    expect(RecordValue::query()->count())->toBe(0);
});
