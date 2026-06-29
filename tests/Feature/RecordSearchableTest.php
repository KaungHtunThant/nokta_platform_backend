<?php

declare(strict_types=1);

use App\DTOs\RecordInput;
use App\Models\EntityType;
use App\Models\Tenant;
use App\Services\Records\RecordWriteService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Phase 4 — the search index payload contains only REPORTABLE fields, scoped per tenant.
 * (The driver is swappable: Meilisearch in production, null in tests — so we assert the payload
 * shape that any engine would index, independent of a running search server.)
 */
it('builds a searchable payload of reportable fields, scoped to the tenant index', function () {
    $tenant = Tenant::create(['slug' => 'alpha', 'name' => ['en' => 'Alpha']]);
    app(TenantManager::class)->set($tenant->id);
    $deal = EntityType::create(['key' => 'deal', 'label' => ['en' => 'Deal']]);
    $deal->fieldDefinitions()->create(['key' => 'title', 'type' => 'text', 'label' => ['en' => 'Title'], 'position' => 0, 'is_reportable' => true]);
    $deal->fieldDefinitions()->create(['key' => 'secret', 'type' => 'text', 'label' => ['en' => 'Secret'], 'position' => 1]); // not reportable

    $record = app(RecordWriteService::class)->create($deal, new RecordInput(null, null, 'open', ['title' => 'Hip surgery', 'secret' => 'hidden']));

    $payload = $record->toSearchableArray();

    expect($payload)->toHaveKey('title', 'Hip surgery')
        ->and($payload)->not->toHaveKey('secret')
        ->and($payload['tenant_id'])->toBe($tenant->id)
        ->and($payload['status'])->toBe('open')
        ->and($record->searchableAs())->toBe('records_tenant_'.$tenant->id);
});
